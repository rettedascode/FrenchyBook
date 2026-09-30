<?php

namespace App\Repository;

use App\Entity\Author;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Author>
 */
class AuthorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Author::class);
    }

    /**
     * Wandelt Namen in Author-Entities um – vorhandene werden wiederverwendet,
     * neue angelegt (persist, aber noch kein flush).
     *
     * @param list<string> $names
     *
     * @return list<Author>
     */
    public function findOrCreateByNames(array $names): array
    {
        $authors = [];
        foreach ($names as $name) {
            $name = Author::normalizeName($name);
            if ('' === $name) {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($authors[$key])) {
                continue;
            }

            $author = $this->findOneByNameInsensitive($name);
            if (null === $author) {
                // Im selben Request schon neu angelegt (noch nicht in der DB)?
                foreach ($this->getEntityManager()->getUnitOfWork()->getScheduledEntityInsertions() as $scheduled) {
                    if ($scheduled instanceof Author && mb_strtolower($scheduled->getName()) === $key) {
                        $author = $scheduled;
                        break;
                    }
                }
            }
            if (null === $author) {
                $author = new Author($name);
                $this->getEntityManager()->persist($author);
            }
            $authors[$key] = $author;
        }

        return array_values($authors);
    }

    public function findOneByNameInsensitive(string $name): ?Author
    {
        return $this->createQueryBuilder('a')
            ->where('LOWER(a.name) = :name')
            ->orWhere('a.name = :exact')
            ->setParameter('name', mb_strtolower($name))
            ->setParameter('exact', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
