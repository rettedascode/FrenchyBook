<?php

namespace App\Model;

use App\Entity\Book;
use App\Enum\BookFormat;

/**
 * Ergebnis einer ISBN-Suche bei Open Library – noch ohne Datenbankbezug.
 */
final class BookLookupResult
{
    public string $isbn = '';
    public string $title = '';
    public ?string $subtitle = null;
    /** @var list<string> */
    public array $authors = [];
    public ?string $publisher = null;
    public ?int $publishedYear = null;
    public ?int $pageCount = null;
    public ?string $language = null;
    public ?string $description = null;
    public ?BookFormat $format = null;
    public ?string $series = null;
    public ?int $seriesNumber = null;
    public ?string $coverUrl = null;

    /** Überträgt die gefundenen Daten auf ein (neues) Buch. Autoren setzt der Aufrufer. */
    public function applyTo(Book $book): void
    {
        $book->setIsbn($this->isbn)
            ->setTitle(mb_substr($this->title, 0, 255))
            ->setSubtitle(null !== $this->subtitle ? mb_substr($this->subtitle, 0, 255) : null)
            ->setPublisher(null !== $this->publisher ? mb_substr($this->publisher, 0, 150) : null)
            ->setPublishedYear($this->publishedYear)
            ->setPageCount($this->pageCount)
            ->setLanguage($this->language)
            ->setDescription($this->description)
            ->setFormat($this->format)
            ->setSeries($this->series)
            ->setSeriesNumber($this->seriesNumber);
    }
}
