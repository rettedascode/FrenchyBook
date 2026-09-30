<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Genre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Genre>
 */
class GenreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Genre::class);
    }

    /** @return list<Genre> */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    /** @return list<Genre> Nur Genres, denen mindestens ein Buch zugeordnet ist. */
    public function findUsed(): array
    {
        return $this->createQueryBuilder('g')
            ->where(sprintf('EXISTS (SELECT b.id FROM %s b WHERE g MEMBER OF b.genres)', Book::class))
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOrCreate(string $name): Genre
    {
        $name = trim($name);
        $genre = $this->createQueryBuilder('g')
            ->where('LOWER(g.name) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $genre) {
            $genre = new Genre(mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1));
            $this->getEntityManager()->persist($genre);
        }

        return $genre;
    }
}
