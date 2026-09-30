<?php

namespace App\Enum;

use Symfony\Component\Intl\Languages;

/**
 * Buchsprachen. Gespeichert wird der ISO-639-1-Code („de“, „fr“ …),
 * angezeigt der Name in der Sprache der Oberfläche („Französisch“ / „français“).
 * Die MARC-Codes entsprechen dem, was Open Library liefert („/languages/fre“).
 */
final class Language
{
    public const MARC_TO_ISO = [
        'ger' => 'de',
        'eng' => 'en',
        'fre' => 'fr',
        'spa' => 'es',
        'ita' => 'it',
        'dut' => 'nl',
        'por' => 'pt',
        'swe' => 'sv',
        'dan' => 'da',
        'nor' => 'no',
        'pol' => 'pl',
        'rus' => 'ru',
        'tur' => 'tr',
        'gre' => 'el',
        'jpn' => 'ja',
        'lat' => 'la',
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_values(self::MARC_TO_ISO);
    }

    /** @return array<string, string> Anzeigename => Code, alphabetisch in der Oberflächensprache */
    public static function choices(string $displayLocale): array
    {
        $choices = [];
        foreach (self::codes() as $code) {
            $choices[self::name($code, $displayLocale)] = $code;
        }
        uksort($choices, static fn (string $a, string $b) => strcoll($a, $b));

        return $choices;
    }

    public static function name(?string $code, string $displayLocale): string
    {
        if (null === $code || '' === $code) {
            return '';
        }

        try {
            $name = Languages::getName($code, $displayLocale);
        } catch (\Throwable) {
            return $code;
        }

        return mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
    }

    public static function fromMarcCode(string $code): ?string
    {
        return self::MARC_TO_ISO[strtolower($code)] ?? null;
    }
}
