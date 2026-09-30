<?php

namespace App\Service;

use App\Enum\BookFormat;
use App\Enum\Language;
use App\Exception\OpenLibraryException;
use App\Model\BookLookupResult;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Sucht Buchdaten per ISBN in der Open Library (https://openlibrary.org).
 *
 * Ablauf: Edition per ISBN laden → parallel Autoren und „Work“ (für Klappentext) nachladen.
 * - Kein Treffer            → null
 * - Timeout/Netzwerkfehler  → OpenLibraryException (Controller zeigt freundlichen Hinweis)
 * - Autor/Work fehlt        → es wird mit dem gearbeitet, was da ist
 */
class OpenLibraryClient
{
    private const COVER_BY_ID = 'https://covers.openlibrary.org/b/id/%d-L.jpg';
    private const COVER_BY_ISBN = 'https://covers.openlibrary.org/b/isbn/%s-L.jpg?default=false';

    public function __construct(
        #[Autowire(service: 'openlibrary.client')]
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws OpenLibraryException wenn Open Library nicht (rechtzeitig) antwortet
     */
    public function findByIsbn(string $isbn): ?BookLookupResult
    {
        $isbn = strtoupper((string) preg_replace('/[^0-9Xx]/', '', $isbn));
        if ('' === $isbn) {
            return null;
        }

        try {
            $response = $this->client->request('GET', '/isbn/'.$isbn.'.json');
            $status = $response->getStatusCode();
            if (404 === $status) {
                return null;
            }
            if ($status >= 400) {
                throw new OpenLibraryException(sprintf('Open Library antwortete mit Status %d.', $status));
            }
            $edition = $response->toArray();
        } catch (HttpExceptionInterface $e) {
            $this->logger->warning('Open-Library-Anfrage fehlgeschlagen.', ['isbn' => $isbn, 'error' => $e->getMessage()]);

            throw new OpenLibraryException('Open Library ist gerade nicht erreichbar.', previous: $e);
        }

        $title = self::text($edition['title'] ?? null);
        if (null === $title) {
            return null;
        }

        // Autoren und Work parallel laden (Symfony HttpClient ist asynchron)
        $authorKeys = array_column($edition['authors'] ?? [], 'key');
        $workKey = $edition['works'][0]['key'] ?? null;
        $authorResponses = array_map(fn (string $key) => $this->client->request('GET', $key.'.json'), \array_slice($authorKeys, 0, 5));
        $workResponse = \is_string($workKey) ? $this->client->request('GET', $workKey.'.json') : null;

        $work = $this->safeArray($workResponse);
        if ([] === $authorResponses && [] !== ($work['authors'] ?? [])) {
            // Manche Editionen haben keine Autoren – dann stehen sie am Work
            $keys = array_filter(array_map(static fn ($a) => $a['author']['key'] ?? null, $work['authors']));
            $authorResponses = array_map(fn (string $key) => $this->client->request('GET', $key.'.json'), \array_slice(array_values($keys), 0, 5));
        }

        $authors = [];
        foreach ($authorResponses as $authorResponse) {
            $name = self::text($this->safeArray($authorResponse)['name'] ?? null);
            if (null !== $name) {
                $authors[] = $name;
            }
        }
        if ([] === $authors && null !== ($by = self::text($edition['by_statement'] ?? null))) {
            $authors[] = rtrim($by, '.');
        }

        $result = new BookLookupResult();
        $result->isbn = $isbn;
        $result->title = $title;
        $result->subtitle = self::text($edition['subtitle'] ?? null);
        $result->authors = $authors;
        $result->publisher = self::text($edition['publishers'][0] ?? null);
        $result->publishedYear = self::year($edition['publish_date'] ?? null);
        $result->pageCount = self::positiveInt($edition['number_of_pages'] ?? null);
        $result->language = self::language($edition['languages'] ?? []);
        $result->description = self::description($edition['description'] ?? null) ?? self::description($work['description'] ?? null);
        $result->format = self::format($edition['physical_format'] ?? null);
        [$result->series, $result->seriesNumber] = self::series($edition['series'][0] ?? null);

        $coverId = self::positiveInt($edition['covers'][0] ?? null);
        $result->coverUrl = null !== $coverId ? sprintf(self::COVER_BY_ID, $coverId) : sprintf(self::COVER_BY_ISBN, $isbn);

        return $result;
    }

    /** Liest eine Neben-Antwort; Fehler dort sollen die Suche nicht scheitern lassen. */
    private function safeArray(?ResponseInterface $response): array
    {
        if (null === $response) {
            return [];
        }
        try {
            return 200 === $response->getStatusCode() ? $response->toArray() : [];
        } catch (HttpExceptionInterface $e) {
            $this->logger->notice('Open Library: Zusatzdaten nicht geladen.', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private static function text(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return '' === $value ? null : $value;
    }

    private static function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private static function year(mixed $publishDate): ?int
    {
        if (\is_string($publishDate) && preg_match('/\b(1[4-9]\d{2}|20\d{2})\b/', $publishDate, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** @param list<array{key?: string}> $languages */
    private static function language(array $languages): ?string
    {
        foreach ($languages as $language) {
            $code = basename((string) ($language['key'] ?? ''));
            if (null !== ($name = Language::fromMarcCode($code))) {
                return $name;
            }
        }

        return null;
    }

    private static function description(mixed $value): ?string
    {
        if (\is_array($value)) {
            $value = $value['value'] ?? null;
        }
        if (!\is_string($value)) {
            return null;
        }
        // Open Library hängt oft Quellen- und Linklisten an – die brauchen wir nicht.
        $value = preg_split('/\n\s*-{3,}/', $value)[0];
        $value = (string) preg_replace('/\(\[source]\[\d+]\)|\[(\d+)]:\s*\S+/', '', $value);
        $value = trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $value)));

        return '' === $value ? null : mb_substr($value, 0, 10000);
    }

    private static function format(mixed $physicalFormat): ?BookFormat
    {
        $f = mb_strtolower((string) $physicalFormat);

        return match (true) {
            '' === $f => null,
            str_contains($f, 'paperback') || str_contains($f, 'taschenbuch') || str_contains($f, 'broschiert') || str_contains($f, 'mass market') => BookFormat::Paperback,
            str_contains($f, 'hardcover') || str_contains($f, 'hardback') || str_contains($f, 'gebunden') => BookFormat::Hardcover,
            str_contains($f, 'ebook') || str_contains($f, 'e-book') || str_contains($f, 'kindle') => BookFormat::Ebook,
            str_contains($f, 'audio') || str_contains($f, 'hörbuch') => BookFormat::Audiobook,
            str_contains($f, 'comic') || str_contains($f, 'graphic') => BookFormat::Comic,
            default => null,
        };
    }

    /** „Harry Potter ; 1“ oder „Die Tribute von Panem, Bd. 2“ → ['Harry Potter', 1] */
    private static function series(mixed $value): array
    {
        $value = self::text($value);
        if (null === $value) {
            return [null, null];
        }
        if (preg_match('/^(.+?)\s*[;,(#]\s*(?:bd\.?|band|vol\.?|volume|book|tome|t\.)?\s*(\d{1,3})\)?\s*$/iu', $value, $m)) {
            return [trim($m[1]), (int) $m[2]];
        }

        return [mb_substr($value, 0, 150), null];
    }
}
