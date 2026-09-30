<?php

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Simulierte Antworten für alle HTTP-Anfragen im Test-Environment
 * (framework.http_client.mock_response_factory).
 *
 * ISBNs:
 *  - 9783551551672 → Treffer (Harry Potter) inkl. Autor, Work-Beschreibung und Cover
 *  - 9783499256356 → kein Treffer (404)
 *  - 9783596164530 → Timeout / Netzwerkfehler
 */
final class FakeOpenLibrary
{
    public const ISBN_FOUND = '9783551551672';
    public const ISBN_NOT_FOUND = '9783499256356';
    public const ISBN_TIMEOUT = '9783596164530';
    public const COVER_URL = 'https://covers.openlibrary.org/b/id/4242-L.jpg';

    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        $path = (string) parse_url($url, \PHP_URL_PATH);

        return match (true) {
            str_ends_with($path, '/isbn/'.self::ISBN_FOUND.'.json') => self::json([
                'title' => 'Harry Potter und der Stein der Weisen',
                'authors' => [['key' => '/authors/OL1A']],
                'works' => [['key' => '/works/OL1W']],
                'publishers' => ['Carlsen'],
                'publish_date' => 'Juli 1998',
                'number_of_pages' => 335,
                'languages' => [['key' => '/languages/ger']],
                'physical_format' => 'Hardcover',
                'series' => ['Harry Potter ; 1'],
                'covers' => [4242],
            ]),
            '/authors/OL1A.json' === $path => self::json(['name' => 'J. K. Rowling']),
            '/works/OL1W.json' === $path => self::json(['description' => [
                'type' => '/type/text',
                'value' => "Ein Junge erfährt, dass er ein Zauberer ist.\n\n----------\nSee also: irgendwas",
            ]]),
            str_ends_with($path, '/isbn/'.self::ISBN_TIMEOUT.'.json') => new MockResponse('', ['error' => 'Idle timeout reached']),
            str_starts_with($path, '/isbn/') => new MockResponse('{"error": "notfound"}', ['http_code' => 404]),
            str_contains($url, 'covers.openlibrary.org/b/id/4242') => new MockResponse(self::coverImage(), [
                'response_headers' => ['content-type' => 'image/jpeg'],
            ]),
            default => new MockResponse('', ['http_code' => 404]),
        };
    }

    public static function coverImage(int $width = 300, int $height = 450): string
    {
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 44, 85, 184));
        for ($i = 0; $i < 400; ++$i) {
            imagesetpixel($im, random_int(0, $width - 1), random_int(0, $height - 1), imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        ob_start();
        imagejpeg($im, null, 90);

        return (string) ob_get_clean();
    }

    private static function json(array $data): MockResponse
    {
        return new MockResponse(json_encode($data, \JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }
}
