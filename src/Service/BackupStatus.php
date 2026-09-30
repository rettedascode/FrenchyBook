<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Liest das Ergebnis des letzten nächtlichen Backups (geschrieben von deploy/frenchybook-backup.sh
 * nach var/backup-status.json) – für die Anzeige in der Verwaltung.
 */
class BackupStatus
{
    /** Älter als das → „überfällig“ (täglicher Lauf + Puffer) */
    private const MAX_AGE_HOURS = 36;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/backup-status.json')] private readonly string $file,
    ) {
    }

    /**
     * @return array{state: 'ok'|'warn'|'error'|'none', result: ?string, time: ?\DateTimeImmutable, size: int, message: ?string}
     */
    public function get(): array
    {
        $data = is_file($this->file) ? json_decode((string) @file_get_contents($this->file), true) : null;
        if (!\is_array($data) || !isset($data['time'])) {
            return ['state' => 'none', 'result' => null, 'time' => null, 'size' => 0, 'message' => null];
        }

        try {
            $time = (new \DateTimeImmutable((string) $data['time']))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        } catch (\Exception) {
            $time = null;
        }
        $result = (string) ($data['result'] ?? 'error');
        $tooOld = null === $time || $time < new \DateTimeImmutable(sprintf('-%d hours', self::MAX_AGE_HOURS));

        return [
            'state' => match (true) {
                'error' === $result => 'error',
                'ok' === $result && !$tooOld => 'ok',
                default => 'warn',
            },
            'result' => $result,
            'time' => $time,
            'size' => (int) ($data['size'] ?? 0),
            'message' => isset($data['message']) ? (string) $data['message'] : null,
        ];
    }
}
