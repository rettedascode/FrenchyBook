<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Lädt den Nutzer beim Anmelden – ausschließlich über den Benutzernamen
     * (Groß-/Kleinschreibung egal). Die E-Mail dient nur für „Passwort vergessen“ und Benachrichtigungen.
     */
    public function loadUserByIdentifier(string $identifier): ?User
    {
        $identifier = trim((string) preg_replace('/\s+/u', ' ', $identifier));

        return '' === $identifier ? null : $this->findOneByNameInsensitive($identifier);
    }

    public function findOneByNameInsensitive(string $name): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.name) = :lower OR u.name = :exact')
            ->setParameter('lower', mb_strtolower($name))
            ->setParameter('exact', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Für die Eindeutigkeitsprüfung des Benutzernamens (UniqueEntity) – unabhängig von Groß-/Kleinschreibung.
     *
     * @param array{name?: string|null} $criteria
     *
     * @return list<User>
     */
    public function findByNameInsensitive(array $criteria): array
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($criteria['name'] ?? '')));
        if ('' === $name) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->where('LOWER(u.name) = :lower OR u.name = :exact')
            ->setParameter('lower', mb_strtolower($name))
            ->setParameter('exact', $name)
            ->getQuery()
            ->getResult();
    }

    /** Aktualisiert den Passwort-Hash automatisch, wenn ein stärkerer Algorithmus verfügbar ist. */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    /** @return list<User> Alle anderen Mitglieder der Gruppe, alphabetisch. */
    public function findAllExcept(User $user): array
    {
        return $this->createQueryBuilder('u')
            ->where('u != :me')
            ->setParameter('me', $user)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<User> Mitglieder, die mindestens ein Buch eingetragen haben. */
    public function findOwnersWithBooks(): array
    {
        return $this->createQueryBuilder('u')
            ->where(sprintf('EXISTS (SELECT b.id FROM %s b WHERE b.owner = u)', Book::class))
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
