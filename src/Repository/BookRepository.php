<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\User;
use App\Enum\BookFormat;
use App\Model\BookFilter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    /**
     * Sucht Bücher für die Kachel-Ansicht.
     *
     * Zweistufig, damit Filter und Seitenweise-Laden exakt bleiben:
     * 1. nur die IDs der passenden Bücher (sortiert, begrenzt),
     * 2. die Bücher samt Autoren und Besitzer in einer Abfrage nachladen.
     *
     * @return array{books: list<Book>, hasMore: bool}
     */
    public function search(BookFilter $filter, User $me): array
    {
        $qb = $this->createQueryBuilder('b')->select('b.id');
        $this->applyFilter($qb, $filter, $me);

        match ($filter->sort) {
            'title' => $qb->addSelect('LOWER(b.title) AS HIDDEN sortTitle')->orderBy('sortTitle', 'ASC'),
            'author' => $qb
                ->addSelect(sprintf('(SELECT MIN(LOWER(sa.name)) FROM %s sb JOIN sb.authors sa WHERE sb = b) AS HIDDEN sortAuthor', Book::class))
                ->addSelect('LOWER(b.title) AS HIDDEN sortTitle')
                ->orderBy('sortAuthor', 'ASC')
                ->addOrderBy('sortTitle', 'ASC'),
            default => $qb->orderBy('b.createdAt', 'DESC'),
        };
        $qb->addOrderBy('b.id', 'DESC')
            ->setFirstResult(($filter->page - 1) * BookFilter::PER_PAGE)
            ->setMaxResults(BookFilter::PER_PAGE + 1);

        $ids = array_map(static fn (array $row) => (int) $row['id'], $qb->getQuery()->getScalarResult());
        $hasMore = \count($ids) > BookFilter::PER_PAGE;
        $ids = \array_slice($ids, 0, BookFilter::PER_PAGE);

        return ['books' => $this->findByIdsKeepingOrder($ids), 'hasMore' => $hasMore];
    }

    public function countMatching(BookFilter $filter, User $me): int
    {
        $qb = $this->createQueryBuilder('b')->select('COUNT(b.id)');
        $this->applyFilter($qb, $filter, $me);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Book>
     */
    public function findByIdsKeepingOrder(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $books = $this->createQueryBuilder('b')
            ->addSelect('o', 'a')
            ->join('b.owner', 'o')
            ->leftJoin('b.authors', 'a')
            ->where('b.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($books as $book) {
            $byId[$book->getId()] = $book;
        }

        return array_values(array_filter(array_map(static fn (int $id) => $byId[$id] ?? null, $ids)));
    }

    /**
     * Lädt ein Buch für die Detailseite mit allem, was dort angezeigt wird.
     */
    public function findForDetail(int $id): ?Book
    {
        return $this->createQueryBuilder('b')
            ->addSelect('o', 'a', 'g', 'l', 'lb')
            ->join('b.owner', 'o')
            ->leftJoin('b.authors', 'a')
            ->leftJoin('b.genres', 'g')
            ->leftJoin('b.loans', 'l')
            ->leftJoin('l.borrower', 'lb')
            ->where('b.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByOwner(User $owner): int
    {
        return $this->count(['owner' => $owner]);
    }

    public function findOneByOwnerAndIsbn(User $owner, string $isbn): ?Book
    {
        return $this->findOneBy(['owner' => $owner, 'isbn' => $isbn]);
    }

    /** @return list<string> Sprachen, die tatsächlich vorkommen (für Filter-Chips) */
    public function findUsedLanguages(): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('DISTINCT b.language')
            ->where('b.language IS NOT NULL')
            ->orderBy('b.language', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values($rows);
    }

    /** @return list<BookFormat> */
    public function findUsedFormats(): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('DISTINCT b.format')
            ->where('b.format IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();

        $formats = array_map(static fn ($f) => $f instanceof BookFormat ? $f : BookFormat::from($f), $rows);

        // In der Reihenfolge des Enums anzeigen
        return array_values(array_filter(BookFormat::cases(), static fn (BookFormat $f) => \in_array($f, $formats, true)));
    }

    private function applyFilter(QueryBuilder $qb, BookFilter $filter, User $me): void
    {
        if ('' !== $filter->q) {
            $term = str_replace(['%', '_'], '', $filter->q);
            $or = $qb->expr()->orX(
                'LOWER(b.title) LIKE :termLower',
                'b.title LIKE :term',
                'LOWER(b.subtitle) LIKE :termLower',
                'LOWER(b.series) LIKE :termLower',
                sprintf('EXISTS (SELECT qa.id FROM %s qb JOIN qb.authors qa WHERE qb = b AND (LOWER(qa.name) LIKE :termLower OR qa.name LIKE :term))', Book::class),
            );
            $qb->setParameter('term', '%'.$term.'%');
            $qb->setParameter('termLower', '%'.mb_strtolower($term).'%');

            $isbn = strtoupper((string) preg_replace('/[^0-9Xx]/', '', $filter->q));
            if (\strlen($isbn) >= 4 && preg_match('/^[0-9\-\s]+[Xx]?$/', $filter->q)) {
                $or->add('b.isbn LIKE :isbn');
                $qb->setParameter('isbn', '%'.$isbn.'%');
            }
            $qb->andWhere($or);
        }

        $activeLoan = sprintf('SELECT al.id FROM %s al WHERE al.book = b AND al.returnedAt IS NULL', Loan::class);
        if ('available' === $filter->status) {
            $qb->andWhere(sprintf('NOT EXISTS (%s)', $activeLoan));
        } elseif ('lent' === $filter->status) {
            $qb->andWhere(sprintf('EXISTS (%s)', $activeLoan));
        }

        // Bezirk: wo das Buch gerade ist – bei der ausleihenden Person (nach der Übergabe), sonst beim Eigentümer
        $district = $filter->district ?? ($filter->near ? $me->getDistrict() : null);
        if (null !== $district) {
            $handedOver = sprintf('SELECT hl.id FROM %s hl WHERE hl.book = b AND hl.returnedAt IS NULL AND hl.handedOverAt IS NOT NULL', Loan::class);
            $atBorrower = sprintf('SELECT dl.id FROM %s dl JOIN dl.borrower dlb WHERE dl.book = b AND dl.returnedAt IS NULL AND dl.handedOverAt IS NOT NULL AND dlb.district = :district', Loan::class);
            $qb->join('b.owner', 'district_owner')
                ->andWhere(sprintf('EXISTS (%s) OR (NOT EXISTS (%s) AND district_owner.district = :district)', $atBorrower, $handedOver))
                ->setParameter('district', $district->value);
        }

        if ($filter->mine) {
            $qb->andWhere('b.owner = :me')->setParameter('me', $me);
        } elseif (null !== $filter->owner) {
            $qb->andWhere('b.owner = :owner')->setParameter('owner', $filter->owner);
        }
        if (null !== $filter->genre) {
            $qb->andWhere(':genre MEMBER OF b.genres')->setParameter('genre', $filter->genre);
        }
        if (null !== $filter->author) {
            $qb->andWhere(':author MEMBER OF b.authors')->setParameter('author', $filter->author);
        }
        if (null !== $filter->language) {
            $qb->andWhere('b.language = :language')->setParameter('language', $filter->language);
        }
        if (null !== $filter->format) {
            $qb->andWhere('b.format = :format')->setParameter('format', $filter->format->value);
        }
    }

    // ------------------------------------------------------------------
    // Verwaltung
    // ------------------------------------------------------------------

    /**
     * Bücher aller Mitglieder, optional nach Titel/ISBN/Autor und Besitzer gefiltert.
     *
     * @return list<Book>
     */
    public function adminSearch(?string $query, ?User $owner, int $limit = 200): array
    {
        $qb = $this->createQueryBuilder('b')
            ->addSelect('o')
            ->join('b.owner', 'o')
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit);
        if (null !== $owner) {
            $qb->andWhere('b.owner = :owner')->setParameter('owner', $owner);
        }
        $query = trim((string) $query);
        if ('' !== $query) {
            $qb->leftJoin('b.authors', 'a')
                ->andWhere('LOWER(b.title) LIKE :q OR b.isbn LIKE :q OR LOWER(a.name) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(addcslashes($query, '%_')).'%')
                ->distinct();
        }

        return $qb->getQuery()->getResult();
    }

    /** @return array<int, int> Nutzer-ID => Anzahl Bücher */
    public function countByAllOwners(): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.owner) AS uid', 'COUNT(b.id) AS n')
            ->groupBy('b.owner')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $r) => ['uid' => (int) $r['uid'], 'n' => (int) $r['n']], $rows), 'n', 'uid');
    }
}
