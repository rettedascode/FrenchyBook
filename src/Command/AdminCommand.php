<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Admin-Rechte vergeben oder entziehen – nötig für den allerersten Admin.
 * Weitere Admins lassen sich danach auch unter /admin/mitglieder ernennen.
 *
 *   php bin/console app:admin Jeremy            zum Admin machen
 *   php bin/console app:admin Jeremy --remove   Admin-Rechte entziehen
 *   php bin/console app:admin --list            alle Admins anzeigen
 */
#[AsCommand(name: 'app:admin', description: 'Macht ein Mitglied zum Admin (oder entzieht die Rechte)')]
final class AdminCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Benutzername')] ?string $name = null,
        #[Option(description: 'Admin-Rechte entziehen')] bool $remove = false,
        #[Option(description: 'Alle Admins anzeigen')] bool $list = false,
    ): int {
        if ($list || null === $name) {
            $admins = array_filter($this->users->findBy([], ['name' => 'ASC']), static fn ($u) => $u->isAdmin());
            $admins
                ? $io->listing(array_map(static fn ($u) => $u->getName().' <'.$u->getEmail().'>', $admins))
                : $io->warning('Es gibt noch keinen Admin. Beispiel: php bin/console app:admin Jeremy');

            return Command::SUCCESS;
        }

        $user = $this->users->findOneByNameInsensitive($name);
        if (null === $user) {
            $io->error(sprintf('Kein Mitglied mit dem Namen „%s“ gefunden.', $name));

            return Command::FAILURE;
        }

        $user->setAdmin(!$remove);
        $this->em->flush();
        $remove
            ? $io->success(sprintf('%s ist kein Admin mehr.', $user->getName()))
            : $io->success(sprintf('%s ist jetzt Admin – die Verwaltung ist unter /admin erreichbar.', $user->getName()));

        return Command::SUCCESS;
    }
}
