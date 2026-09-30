<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WaitlistEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WaitlistEntry>
 */
class WaitlistEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WaitlistEntry::class);
    }

    /** @return list<WaitlistEntry> Wartelisten-Plätze einer Person (für die Übersicht) */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('w')
            ->addSelect('b', 'o')
            ->join('w.book', 'b')
            ->join('b.owner', 'o')
            ->where('w.user = :u')
            ->setParameter('u', $user)
            ->orderBy('w.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
