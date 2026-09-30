<?php

namespace App\Service;

use App\Exception\CoverException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Liip\ImagineBundle\Model\Binary;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Speichert Buchcover lokal unter public/uploads/covers.
 *
 * Jedes Bild (Upload oder Download von Open Library) wird serverseitig
 * richtig gedreht (EXIF), von Metadaten befreit, auf max. 1200×1800 px verkleinert
 * und als WebP gespeichert. Thumbnails erzeugt LiipImagine bei Bedarf.
 */
class CoverManager
{
    public const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
    private const MAX_DOWNLOAD_BYTES = 10 * 1024 * 1024;
    private const ALLOWED_DOWNLOAD_HOSTS = ['covers.openlibrary.org'];
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly FilterManager $filterManager,
        private readonly CacheManager $cacheManager,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.covers_dir%')]
        private readonly string $coversDir,
        #[Autowire('%app.covers_public_path%')]
        private readonly string $coversPublicPath,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * @throws CoverException mit nutzerfreundlicher, deutscher Meldung
     */
    public function storeUpload(UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new CoverException('cover.error.upload');
        }
        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw new CoverException('cover.error.too_large');
        }

        return $this->storeBinary((string) file_get_contents($file->getPathname()), (string) $file->getMimeType());
    }

    /**
     * Lädt ein Cover (nur von Open Library) herunter und speichert es lokal.
     * Gibt null zurück, wenn es kein brauchbares Bild gibt – das Buch bekommt dann den Platzhalter.
     */
    public function storeFromUrl(string $url): ?string
    {
        $host = parse_url($url, \PHP_URL_HOST);
        if ('https' !== parse_url($url, \PHP_URL_SCHEME) || !\in_array($host, self::ALLOWED_DOWNLOAD_HOSTS, true)) {
            $this->logger->warning('Cover-Download von nicht erlaubter Adresse abgelehnt.', ['url' => $url]);

            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 6,
                'max_duration' => 12,
                'max_redirects' => 5,
                'headers' => ['User-Agent' => 'FrenchyBook/1.0 (private book sharing app)'],
            ]);
            if (200 !== $response->getStatusCode()) {
                return null;
            }
            $length = (int) ($response->getHeaders()['content-length'][0] ?? 0);
            if ($length > self::MAX_DOWNLOAD_BYTES) {
                return null;
            }
            $content = $response->getContent();
        } catch (HttpExceptionInterface $e) {
            $this->logger->notice('Cover konnte nicht geladen werden.', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        if (\strlen($content) > self::MAX_DOWNLOAD_BYTES || \strlen($content) < 1000) {
            // Winzige Antworten sind Platzhalter-Pixel von Open Library
            return null;
        }

        $mimeType = (new \finfo(\FILEINFO_MIME_TYPE))->buffer($content) ?: '';
        try {
            return $this->storeBinary($content, $mimeType);
        } catch (CoverException) {
            return null;
        }
    }

    /**
     * @throws CoverException
     */
    public function storeBinary(string $content, string $mimeType): string
    {
        if (!\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new CoverException('cover.error.format');
        }

        $size = @getimagesizefromstring($content);
        if (false === $size || $size[0] < 20 || $size[1] < 20) {
            throw new CoverException('cover.error.unreadable');
        }
        if ($size[0] * $size[1] > 40_000_000) {
            throw new CoverException('cover.error.too_many_pixels');
        }

        try {
            $binary = new Binary($content, $mimeType, explode('/', $mimeType)[1]);
            $processed = $this->filterManager->applyFilter($binary, 'cover_original');
        } catch (\Throwable $e) {
            $this->logger->error('Cover konnte nicht verarbeitet werden.', ['exception' => $e]);

            throw new CoverException('cover.error.processing');
        }

        $filename = sprintf('%s-%s.%s', date('Ymd'), bin2hex(random_bytes(8)), $processed->getFormat() ?: 'webp');
        $this->filesystem->dumpFile($this->coversDir.'/'.$filename, $processed->getContent());

        return $filename;
    }

    /** Löscht Original und alle erzeugten Thumbnails. */
    public function remove(?string $filename): void
    {
        if (null === $filename || '' === $filename || str_contains($filename, '/') || str_contains($filename, '\\')) {
            return;
        }

        $this->cacheManager->remove($this->publicPath($filename));
        $this->filesystem->remove($this->coversDir.'/'.$filename);
    }

    /** Pfad relativ zu public/, z. B. „uploads/covers/abc.webp“ (für Twig/LiipImagine). */
    public function publicPath(string $filename): string
    {
        return $this->coversPublicPath.'/'.$filename;
    }
}
