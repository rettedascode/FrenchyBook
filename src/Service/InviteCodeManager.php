<?php

namespace App\Service;

use App\Repository\SettingRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Der gemeinsame Einladungscode für die Registrierung.
 *
 * Gespeichert in der Datenbank, damit er in der Verwaltung geändert werden kann.
 * Solange dort nichts gespeichert ist, gilt INVITE_CODE aus .env.local.
 * Leerer Code = jeder kann sich registrieren.
 */
class InviteCodeManager
{
    public const SETTING = 'invite_code';

    /** Ohne leicht verwechselbare Zeichen (0/O, 1/l/I) – gut zum Abtippen und Vorlesen. */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    private const PREFIX = 'frenchy-';
    private const LENGTH = 6;
    public const PATTERN = '/^[\p{L}\p{N}_-]{4,40}$/u';

    public function __construct(
        private readonly SettingRepository $settings,
        #[Autowire('%env(INVITE_CODE)%')]
        private readonly string $envCode,
    ) {
    }

    public function current(): string
    {
        $setting = $this->settings->getValue(self::SETTING);

        return trim(null !== $setting ? (string) $setting->getValue() : $this->envCode);
    }

    public function isRequired(): bool
    {
        return '' !== $this->current();
    }

    /** Neuen Zufallscode erzeugen – der alte ist sofort ungültig. */
    public function regenerate(): string
    {
        $part = '';
        for ($i = 0; $i < self::LENGTH; ++$i) {
            $part .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
        }
        $this->set(self::PREFIX.$part);

        return $this->current();
    }

    /**
     * @throws \InvalidArgumentException bei ungültigem Code
     */
    public function set(string $code): void
    {
        $code = trim($code);
        if ('' !== $code && !preg_match(self::PATTERN, $code)) {
            throw new \InvalidArgumentException('Der Code muss 4–40 Zeichen lang sein und darf nur Buchstaben, Ziffern, - und _ enthalten.');
        }
        $this->settings->setValue(self::SETTING, $code);
    }

    /** Code-Pflicht abschalten */
    public function disable(): void
    {
        $this->set('');
    }
}
