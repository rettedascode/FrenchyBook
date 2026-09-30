<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Liest das Fehlerprotokoll für die Verwaltung (var/log/errors-JJJJ-MM-TT.log, JSON-Zeilen von Monolog).
 * Gleiche Fehler werden zusammengefasst: „5× – zuletzt heute 14:02“.
 */
class ErrorLog
{
    private const MAX_LINES = 5000;

    public function __construct(
        #[Autowire('%kernel.logs_dir%')] private readonly string $logsDir,
    ) {
    }

    /**
     * @return list<array{level: string, channel: string, message: string, detail: ?string, where: ?string, context: array<string, mixed>, count: int, first: \DateTimeImmutable, last: \DateTimeImmutable}>
     */
    public function grouped(int $days = 14): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d days', $days));
        $groups = [];
        foreach ($this->entries($since) as $entry) {
            $key = md5($entry['level'].'|'.$entry['channel'].'|'.$entry['message'].'|'.$entry['detail']);
            if (!isset($groups[$key])) {
                $groups[$key] = $entry + ['count' => 0, 'first' => $entry['time'], 'last' => $entry['time']];
            }
            ++$groups[$key]['count'];
            if ($entry['time'] < $groups[$key]['first']) {
                $groups[$key]['first'] = $entry['time'];
            }
            if ($entry['time'] > $groups[$key]['last']) {
                $groups[$key]['last'] = $entry['time'];
                $groups[$key]['context'] = $entry['context'];
            }
        }
        usort($groups, static fn (array $a, array $b) => $b['last'] <=> $a['last']);

        return array_map(static function (array $g) {
            unset($g['time']);

            return $g;
        }, $groups);
    }

    public function countSince(\DateTimeImmutable $since): int
    {
        return \count($this->entries($since));
    }

    /** Löscht alle Protokolldateien, z. B. nachdem ein Fehler behoben ist. */
    public function clear(): void
    {
        foreach ($this->files() as $file) {
            @unlink($file);
        }
    }

    /**
     * @return list<array{time: \DateTimeImmutable, level: string, channel: string, message: string, detail: ?string, where: ?string, context: array<string, mixed>}>
     */
    private function entries(\DateTimeImmutable $since): array
    {
        $entries = [];
        foreach ($this->files() as $file) {
            // Dateiname enthält das Datum – ältere Dateien gar nicht erst lesen
            if (preg_match('/errors-(\d{4}-\d{2}-\d{2})\.log$/', $file, $m) && $m[1] < $since->format('Y-m-d')) {
                continue;
            }
            $lines = @file($file, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) ?: [];
            foreach (\array_slice($lines, -self::MAX_LINES) as $line) {
                $data = json_decode($line, true);
                if (!\is_array($data) || !isset($data['datetime'], $data['message'])) {
                    continue;
                }
                try {
                    $time = (new \DateTimeImmutable($data['datetime']))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                } catch (\Exception) {
                    continue;
                }
                if ($time < $since) {
                    continue;
                }
                $context = \is_array($data['context'] ?? null) ? $data['context'] : [];
                $exception = \is_array($context['exception'] ?? null) ? $context['exception'] : null;
                unset($context['exception']);

                $entries[] = [
                    'time' => $time,
                    'level' => strtolower((string) ($data['level_name'] ?? 'error')),
                    'channel' => (string) ($data['channel'] ?? 'app'),
                    'message' => mb_substr((string) $data['message'], 0, 500),
                    'detail' => null !== $exception ? mb_substr(trim(($exception['class'] ?? '').': '.($exception['message'] ?? '')), 0, 500) : null,
                    'where' => null !== $exception && isset($exception['file']) ? self::shortPath((string) $exception['file']) : null,
                    'context' => $context,
                ];
            }
        }

        return $entries;
    }

    /** @return list<string> */
    private function files(): array
    {
        return glob($this->logsDir.'/errors*.log') ?: [];
    }

    private static function shortPath(string $file): string
    {
        $pos = strpos($file, '/src/') ?: strpos($file, '/vendor/') ?: strpos($file, '\\src\\') ?: strpos($file, '\\vendor\\');

        return false !== $pos ? ltrim(substr($file, $pos), '/\\') : basename($file);
    }
}
