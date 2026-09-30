<?php

namespace App\Command;

use App\Service\EnvLocalWriter;
use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Richtet die Schlüssel für Push-Benachrichtigungen (VAPID) ein.
 *
 *   php bin/console app:push-keys          Schlüssel erzeugen, falls noch keine da sind
 *   php bin/console app:push-keys --show   öffentlichen Schlüssel anzeigen
 *   php bin/console app:push-keys --force  neue Schlüssel erzeugen (alle Geräte müssen Push neu aktivieren!)
 *
 * Auf dem Server als root ausführen, weil nur root .env.local schreiben darf.
 */
#[AsCommand(
    name: 'app:push-keys',
    description: 'Erzeugt die Schlüssel für Push-Benachrichtigungen (VAPID)',
)]
final class PushKeysCommand
{
    public function __construct(private readonly EnvLocalWriter $env)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Nur den öffentlichen Schlüssel anzeigen')] bool $show = false,
        #[Option(description: 'Vorhandene Schlüssel ersetzen – alle Geräte müssen Push danach neu aktivieren')] bool $force = false,
        #[Option(description: 'Kontaktadresse für die Push-Dienste (mailto:… oder https://…)')] ?string $subject = null,
    ): int {
        $current = $this->env->get('VAPID_PUBLIC_KEY');

        if ($show) {
            '' === $current
                ? $io->warning('Es sind noch keine Push-Schlüssel eingerichtet. Führe den Befehl ohne --show aus.')
                : $io->success('Öffentlicher Schlüssel: '.$current);

            return Command::SUCCESS;
        }

        if ('' !== $current && !$force) {
            $io->success('Push-Schlüssel sind schon eingerichtet – nichts zu tun.');
            $io->note('Mit --force erzeugst du neue Schlüssel. Dann müssen alle Push auf ihren Geräten neu aktivieren.');

            return Command::SUCCESS;
        }

        $keys = VAPID::createVapidKeys();
        $values = [
            'VAPID_PUBLIC_KEY' => $keys['publicKey'],
            'VAPID_PRIVATE_KEY' => $keys['privateKey'],
        ];
        if (null !== $subject) {
            $values['VAPID_SUBJECT'] = $subject;
        }

        try {
            $this->env->set($values);
        } catch (\RuntimeException $e) {
            $io->error([$e->getMessage(), 'Tipp: Auf dem Server als root ausführen (sudo).']);

            return Command::FAILURE;
        }

        $io->success('Push-Schlüssel erzeugt und in .env.local gespeichert.');
        if ($force && '' !== $current) {
            $io->warning('Alte Push-Abos sind ab jetzt ungültig – alle müssen Push im Profil neu aktivieren.');
        }

        return Command::SUCCESS;
    }
}
