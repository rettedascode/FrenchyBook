<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Merkt sich die letzte Anmeldung (auch die automatische über „Angemeldet bleiben“),
 * damit man in der Verwaltung sieht, wer FrenchyBook noch nutzt.
 */
#[AsEventListener]
final class LastLoginSubscriber
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }
        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->em->flush();
    }
}
