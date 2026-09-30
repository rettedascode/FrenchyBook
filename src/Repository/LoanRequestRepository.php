<?php

namespace App\Repository;

use App\Entity\LoanRequest;
use App\Entity\User;
use App\Enum\LoanRequestStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanRequest>
 */
class LoanRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanRequest::class);
    }

    /**
     * Offene Anfragen an mich (für meine Bücher), älteste zuerst.
     *
     * @return list<LoanRequest>
     */
    public function findOpenForOwner(User $owner): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('b', 'u', 'a')
            ->join('r.book', 'b')
            ->join('r.requester', 'u')
            ->leftJoin('b.authors', 'a')
            ->where('b.owner = :owner')
            ->andWhere('r.status = :open')
            ->setParameter('owner', $owner)
            ->setParameter('open', LoanRequestStatus::Open)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countOpenForOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.book', 'b')
            ->where('b.owner = :owner')
            ->andWhere('r.status = :open')
            ->setParameter('owner', $owner)
            ->setParameter('open', LoanRequestStatus::Open)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Meine eigenen, noch unbeantworteten Anfragen.
     *
     * @return list<LoanRequest>
     */
    public function findOpenByRequester(User $requester): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('b', 'o', 'a')
            ->join('r.book', 'b')
            ->join('b.owner', 'o')
            ->leftJoin('b.authors', 'a')
            ->where('r.requester = :me')
            ->andWhere('r.status = :open')
            ->setParameter('me', $requester)
            ->setParameter('open', LoanRequestStatus::Open)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ------------------------------------------------------------------
    // Verwaltung
    // ------------------------------------------------------------------

    /** @return list<LoanRequest> alle offenen Anfragen, älteste zuerst */
    public function findAllOpen(): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('b', 'u', 'o')
            ->join('r.book', 'b')
            ->join('r.requester', 'u')
            ->join('b.owner', 'o')
            ->where('r.status = :open')
            ->setParameter('open', LoanRequestStatus::Open)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countOpen(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.status = :open')
            ->setParameter('open', LoanRequestStatus::Open)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
