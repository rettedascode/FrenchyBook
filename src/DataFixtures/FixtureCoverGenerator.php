<?php

namespace App\DataFixtures;

/**
 * Erzeugt ein schlichtes Cover-Bild (Farbverlauf + Titel), falls für die Beispieldaten
 * kein echtes Cover von Open Library geladen werden kann (z. B. offline).
 */
final class FixtureCoverGenerator
{
    private const FONT_CANDIDATES = [
        'C:/Windows/Fonts/georgiab.ttf',
        'C:/Windows/Fonts/arialbd.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        '/System/Library/Fonts/Supplemental/Georgia Bold.ttf',
        '/Library/Fonts/Arial Bold.ttf',
    ];

    /** @return string JPEG-Binärdaten */
    public static function generate(string $title, string $author, string $hexColor): string
    {
        $w = 600;
        $h = 900;
        $im = imagecreatetruecolor($w, $h);
        [$r, $g, $b] = sscanf($hexColor, '#%02x%02x%02x');

        // Vertikaler Verlauf vom Grundton ins Dunklere
        for ($y = 0; $y < $h; ++$y) {
            $f = 1 - 0.45 * ($y / $h);
            imageline($im, 0, $y, $w, $y, imagecolorallocate($im, (int) ($r * $f), (int) ($g * $f), (int) ($b * $f)));
        }
        $white = imagecolorallocate($im, 255, 255, 255);
        $soft = imagecolorallocatealpha($im, 255, 255, 255, 70);
        imagefilledrectangle($im, 60, 120, $w - 60, 124, $soft);
        imagefilledrectangle($im, 60, $h - 170, $w - 60, $h - 167, $soft);

        $font = null;
        foreach (self::FONT_CANDIDATES as $candidate) {
            if (is_file($candidate)) {
                $font = $candidate;
                break;
            }
        }

        if (null !== $font && \function_exists('imagettftext')) {
            $y = 220;
            foreach (self::wrap($title, 16) as $line) {
                imagettftext($im, 44, 0, 60, $y, $white, $font, $line);
                $y += 64;
            }
            imagettftext($im, 22, 0, 60, $h - 110, $white, $font, mb_strimwidth($author, 0, 34, "…"));
        } else {
            $y = 200;
            foreach (self::wrap($title, 30) as $line) {
                imagestring($im, 5, 60, $y, $line, $white);
                $y += 24;
            }
            imagestring($im, 4, 60, $h - 120, $author, $white);
        }

        ob_start();
        imagejpeg($im, null, 88);

        return (string) ob_get_clean();
    }

    /** @return list<string> */
    private static function wrap(string $text, int $width): array
    {
        return \array_slice(explode("\n", wordwrap($text, $width, "\n", true)), 0, 6);
    }
}
