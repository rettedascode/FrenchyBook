<?php

namespace App\Repository;

use App\Entity\PushSubscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushSubscription>
 */
class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    public function findOneByEndpoint(string $endpoint): ?PushSubscription
    {
        return $this->findOneBy(['endpointHash' => PushSubscription::hash($endpoint)]);
    }

    /** @return list<PushSubscription> */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'ASC']);
    }
}
