<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Die PDF-Anleitungen (DE/FR) für die Verwaltung.
 *
 * Sie liegen geschützt auf dem Server in var/guides/ (nicht öffentlich erreichbar) – hochgeladen
 * per „make guides-upload“, von dort zusätzlich nach Cloudflare R2 gesichert.
 */
class GuideFiles
{
    private const NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]*\.pdf$/';

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/guides')] private readonly string $dir,
    ) {
    }

    /**
     * @return list<array{name: string, size: int, updated: \DateTimeImmutable}>
     */
    public function all(): array
    {
        $files = [];
        foreach (glob($this->dir.'/*.pdf') ?: [] as $path) {
            $name = basename($path);
            if (!preg_match(self::NAME_PATTERN, $name) || !is_file($path)) {
                continue;
            }
            $files[] = [
                'name' => $name,
                'size' => (int) filesize($path),
                'updated' => (new \DateTimeImmutable('@'.filemtime($path)))->setTimezone(new \DateTimeZone(date_default_timezone_get())),
            ];
        }
        usort($files, static fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $files;
    }

    /** Vollständiger Pfad einer Anleitung – oder null, wenn es sie nicht gibt (kein Ausbruch aus dem Ordner möglich). */
    public function path(string $name): ?string
    {
        if (!preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }
        $path = $this->dir.'/'.$name;

        return is_file($path) ? $path : null;
    }
}
