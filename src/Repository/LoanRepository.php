<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Loan>
 */
class LoanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Loan::class);
    }

    /**
     * Aktive Ausleihen für mehrere Bücher auf einmal (vermeidet N+1-Abfragen im Grid).
     *
     * @param list<Book> $books
     *
     * @return array<int, Loan> Buch-ID => aktive Ausleihe
     */
    public function findActiveByBooks(array $books): array
    {
        if ([] === $books) {
            return [];
        }

        $loans = $this->createQueryBuilder('l')
            ->addSelect('u')
            ->join('l.borrower', 'u')
            ->where('l.book IN (:books)')
            ->andWhere('l.returnedAt IS NULL')
            ->setParameter('books', $books)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($loans as $loan) {
            $map[$loan->getBook()->getId()] = $loan;
        }

        return $map;
    }

    public function findActiveForBook(Book $book): ?Loan
    {
        return $this->createQueryBuilder('l')
            ->where('l.book = :book')
            ->andWhere('l.returnedAt IS NULL')
            ->setParameter('book', $book)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Bücher, die ich verliehen habe – überfällige zuerst.
     *
     * @return list<Loan>
     */
    public function findActiveLentBy(User $owner): array
    {
        $loans = $this->createQueryBuilder('l')
            ->addSelect('b', 'u', 'a')
            ->join('l.book', 'b')
            ->join('l.borrower', 'u')
            ->leftJoin('b.authors', 'a')
            ->where('b.owner = :owner')
            ->andWhere('l.returnedAt IS NULL')
            ->setParameter('owner', $owner)
            ->orderBy('l.lentAt', 'ASC')
            ->getQuery()
            ->getResult();

        return self::sortOverdueFirst($loans);
    }

    /**
     * Bücher, die ich mir gerade geliehen habe.
     *
     * @return list<Loan>
     */
    public function findActiveBorrowedBy(User $borrower): array
    {
        $loans = $this->createQueryBuilder('l')
            ->addSelect('b', 'o', 'a')
            ->join('l.book', 'b')
            ->join('b.owner', 'o')
            ->leftJoin('b.authors', 'a')
            ->where('l.borrower = :borrower')
            ->andWhere('l.returnedAt IS NULL')
            ->setParameter('borrower', $borrower)
            ->orderBy('l.lentAt', 'ASC')
            ->getQuery()
            ->getResult();

        return self::sortOverdueFirst($loans);
    }

    /**
     * Alle überfälligen, noch nicht zurückgegebenen Ausleihen.
     *
     * @return list<Loan>
     */
    public function findOverdue(\DateTimeImmutable $today): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('b', 'u', 'o')
            ->join('l.book', 'b')
            ->join('l.borrower', 'u')
            ->join('b.owner', 'o')
            ->where('l.returnedAt IS NULL')
            ->andWhere('l.dueAt IS NOT NULL')
            ->andWhere('l.dueAt < :today')
            ->setParameter('today', $today->setTime(0, 0), 'date_immutable')
            ->orderBy('l.dueAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActiveBorrowedBy(User $user): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.borrower = :u')
            ->andWhere('l.returnedAt IS NULL')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveLentBy(User $owner): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->join('l.book', 'b')
            ->where('b.owner = :owner')
            ->andWhere('l.returnedAt IS NULL')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<Loan> $loans
     *
     * @return list<Loan>
     */
    private static function sortOverdueFirst(array $loans): array
    {
        usort($loans, static function (Loan $a, Loan $b): int {
            if ($a->isOverdue() !== $b->isOverdue()) {
                return $a->isOverdue() ? -1 : 1;
            }
            $dueA = $a->getDueAt()?->getTimestamp() ?? \PHP_INT_MAX;
            $dueB = $b->getDueAt()?->getTimestamp() ?? \PHP_INT_MAX;

            return [$dueA, $a->getLentAt()] <=> [$dueB, $b->getLentAt()];
        });

        return $loans;
    }

    // ------------------------------------------------------------------
    // Verwaltung
    // ------------------------------------------------------------------

    /**
     * Alle laufenden Ausleihen – überfällige zuerst.
     *
     * @return list<Loan>
     */
    public function findAllActive(): array
    {
        $loans = $this->createQueryBuilder('l')
            ->addSelect('b', 'u', 'o')
            ->join('l.book', 'b')
            ->join('l.borrower', 'u')
            ->join('b.owner', 'o')
            ->where('l.returnedAt IS NULL')
            ->orderBy('l.lentAt', 'DESC')
            ->getQuery()
            ->getResult();
        usort($loans, static fn (Loan $a, Loan $b) => $b->isOverdue() <=> $a->isOverdue());

        return $loans;
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.returnedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<int, int> Nutzer-ID => Anzahl gerade geliehener Bücher */
    public function countActiveByBorrower(): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.borrower) AS uid', 'COUNT(l.id) AS n')
            ->where('l.returnedAt IS NULL')
            ->groupBy('l.borrower')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $r) => ['uid' => (int) $r['uid'], 'n' => (int) $r['n']], $rows), 'n', 'uid');
    }

    /**
     * Ausleihen, deren Rückgabetermin in den nächsten $days Tagen liegt und für die
     * noch keine Vorab-Erinnerung verschickt wurde.
     *
     * @return list<Loan>
     */
    public function findDueSoon(\DateTimeImmutable $today, int $days): array
    {
        $today = $today->setTime(0, 0);

        return $this->createQueryBuilder('l')
            ->addSelect('b', 'u', 'o')
            ->join('l.book', 'b')
            ->join('l.borrower', 'u')
            ->join('b.owner', 'o')
            ->where('l.returnedAt IS NULL')
            ->andWhere('l.dueSoonReminderSentAt IS NULL')
            ->andWhere('l.dueAt >= :today')
            ->andWhere('l.dueAt <= :until')
            ->setParameter('today', $today, 'date_immutable')
            ->setParameter('until', $today->modify(sprintf('+%d days', $days)), 'date_immutable')
            ->orderBy('l.dueAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
