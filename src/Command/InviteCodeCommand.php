<?php

namespace App\Command;

use App\Service\InviteCodeManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Verwaltet den gemeinsamen Einladungscode für die Registrierung
 * (dasselbe geht auch in der Verwaltung unter /admin).
 *
 *   php bin/console app:invite-code            neuen Code erzeugen (alter wird sofort ungültig)
 *   php bin/console app:invite-code --show     aktuellen Code anzeigen
 *   php bin/console app:invite-code --set=abc  eigenen Code festlegen
 *   php bin/console app:invite-code --off      Code-Pflicht abschalten (jeder kann sich registrieren)
 */
#[AsCommand(
    name: 'app:invite-code',
    description: 'Erzeugt, zeigt oder setzt den Einladungscode für die Registrierung',
)]
final class InviteCodeCommand
{
    public function __construct(
        private readonly InviteCodeManager $inviteCodes,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Nur den aktuellen Code anzeigen')] bool $show = false,
        #[Option(description: 'Einen selbst gewählten Code setzen')] ?string $set = null,
        #[Option(description: 'Code-Pflicht abschalten – jeder kann sich registrieren')] bool $off = false,
    ): int {
        if ($show) {
            $current = $this->inviteCodes->current();
            '' === $current
                ? $io->warning('Es ist kein Einladungscode gesetzt – jeder kann sich registrieren.')
                : $this->printCode($io, $current, 'Aktueller Einladungscode');

            return Command::SUCCESS;
        }

        if ($off) {
            if (!$io->confirm('Wirklich abschalten? Dann kann sich jeder mit dem Link registrieren.', false)) {
                $io->note('Abgebrochen – der Einladungscode bleibt unverändert.');

                return Command::SUCCESS;
            }
            $this->inviteCodes->disable();
            $io->warning('Die Code-Pflicht ist abgeschaltet – jeder kann sich registrieren.');

            return Command::SUCCESS;
        }

        if (null !== $set) {
            try {
                $this->inviteCodes->set($set);
            } catch (\InvalidArgumentException $e) {
                $io->error($e->getMessage());

                return Command::INVALID;
            }
        } else {
            $this->inviteCodes->regenerate();
        }

        $this->printCode($io, $this->inviteCodes->current(), 'Neuer Einladungscode');
        $io->note('Der alte Code ist ab sofort ungültig. Bereits registrierte Nutzer sind nicht betroffen.');

        return Command::SUCCESS;
    }

    private function printCode(SymfonyStyle $io, string $code, string $title): void
    {
        $io->success($title.': '.$code);
        $io->text([
            'So lädst du Freunde ein – schick ihnen:',
            '  Link:  '.$this->urlGenerator->generate('app_register', [], UrlGeneratorInterface::ABSOLUTE_URL),
            '  Code:  '.$code,
            '',
            'Alle angemeldeten Mitglieder sehen den Code außerdem in ihrem Profil.',
        ]);
    }
}
