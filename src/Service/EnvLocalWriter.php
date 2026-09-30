<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Liest und schreibt einzelne Einstellungen in .env.local – und, falls vorhanden,
 * in die kompilierte .env.local.php (composer dump-env prod). Rechte und Gruppe der
 * Dateien bleiben erhalten; geschrieben wird atomar über eine temporäre Datei.
 *
 * Auf dem Server als root ausführen, weil nur root diese Dateien schreiben darf.
 */
class EnvLocalWriter
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function get(string $key): string
    {
        $phpFile = $this->projectDir.'/.env.local.php';
        if (is_file($phpFile)) {
            $vars = require $phpFile;

            return (string) ($vars[$key] ?? '');
        }
        $envFile = $this->projectDir.'/.env.local';
        if (is_file($envFile) && preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', (string) file_get_contents($envFile), $m)) {
            return trim($m[1], " \t\"'");
        }

        return (string) ($_SERVER[$key] ?? $_ENV[$key] ?? '');
    }

    /**
     * @param array<string, string> $values
     *
     * @throws \RuntimeException mit verständlicher Meldung (z. B. fehlende Schreibrechte)
     */
    public function set(array $values): void
    {
        $envFile = $this->projectDir.'/.env.local';
        $content = is_file($envFile) ? (string) file_get_contents($envFile) : '';
        foreach ($values as $key => $value) {
            $line = $key.'='.(preg_match('/[\s#"\'$]/', $value) ? '"'.addcslashes($value, '"\\$').'"' : $value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content)
                ? (string) preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $content)
                : rtrim($content)."\n".$line."\n";
        }
        $this->write($envFile, ltrim($content));

        $phpFile = $this->projectDir.'/.env.local.php';
        if (is_file($phpFile)) {
            $vars = require $phpFile;
            if (!\is_array($vars)) {
                throw new \RuntimeException(sprintf('%s hat ein unerwartetes Format.', $phpFile));
            }
            $vars = array_merge($vars, $values);
            $this->write($phpFile, "<?php\n\n// Generiert von \"composer dump-env prod\", zuletzt geändert von einem FrenchyBook-Befehl\n\nreturn ".var_export($vars, true).";\n");
            if (\function_exists('opcache_invalidate')) {
                opcache_invalidate($phpFile, true);
            }
        }
    }

    private function write(string $file, string $content): void
    {
        if (is_file($file) && !is_writable($file)) {
            throw new \RuntimeException(sprintf('Keine Schreibrechte für %s.', $file));
        }
        $perms = is_file($file) ? fileperms($file) & 0777 : 0640;
        $group = is_file($file) ? filegroup($file) : false;

        $tmp = $file.'.tmp'.bin2hex(random_bytes(4));
        if (false === file_put_contents($tmp, $content)) {
            throw new \RuntimeException(sprintf('%s konnte nicht geschrieben werden.', $file));
        }
        chmod($tmp, $perms);
        if (false !== $group) {
            @chgrp($tmp, $group);
        }
        rename($tmp, $file);
    }
}
