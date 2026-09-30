<?php

namespace App\Util;

/**
 * Datumsformate je Sprache: Deutsch 26.10.2026, Französisch 26/10/2026.
 */
final class LocalizedDate
{
    private const FORMATS = [
        'de' => ['long' => 'd.m.Y', 'short' => 'd.m.'],
        'fr' => ['long' => 'd/m/Y', 'short' => 'd/m'],
    ];

    public static function format(?\DateTimeInterface $date, string $locale, string $style = 'long'): string
    {
        if (null === $date) {
            return '';
        }
        $formats = self::FORMATS[$locale] ?? self::FORMATS['de'];

        return $date->format($formats[$style] ?? $formats['long']);
    }
}
