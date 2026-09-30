<?php

namespace App\Model;

use App\Enum\BookFormat;
use App\Enum\District;
use Symfony\Component\HttpFoundation\Request;

/**
 * Suchbegriff, Filter-Chips und Sortierung der Bücherliste – direkt aus der URL gelesen,
 * damit sich jede Ansicht teilen und per Zurück-Button wiederherstellen lässt.
 */
final class BookFilter
{
    /** Sortierung => Übersetzungsschlüssel */
    public const SORTS = [
        'new' => 'books.sort.new',
        'title' => 'books.sort.title',
        'author' => 'books.sort.author',
    ];

    public const PER_PAGE = 30;

    public string $q = '';
    public ?string $status = null;      // 'available' | 'lent'
    public bool $mine = false;
    public ?int $genre = null;
    public ?string $language = null;
    public ?BookFormat $format = null;
    public ?int $owner = null;
    public ?int $author = null;
    /** Kölner Bezirk, in dem sich das Buch gerade befindet */
    public ?District $district = null;
    /** Schnellfilter „In meinem Bezirk“ (Bezirk aus dem eigenen Profil) */
    public bool $near = false;
    public string $sort = 'new';
    public int $page = 1;

    public static function fromRequest(Request $request): self
    {
        $q = $request->query;
        $filter = new self();
        $filter->q = mb_substr(trim($q->getString('q')), 0, 100);
        $filter->status = \in_array($q->get('status'), ['available', 'lent'], true) ? $q->get('status') : null;
        $filter->mine = $q->getBoolean('mine');
        $filter->genre = self::positiveInt($q->get('genre'));
        $filter->language = '' !== $q->getString('language') ? mb_substr($q->getString('language'), 0, 40) : null;
        $filter->format = BookFormat::tryFrom($q->getString('format'));
        $filter->owner = self::positiveInt($q->get('owner'));
        $filter->author = self::positiveInt($q->get('author'));
        $filter->district = District::tryFrom($q->getString('bezirk'));
        $filter->near = $q->getBoolean('nah');
        $filter->sort = \array_key_exists($q->getString('sort'), self::SORTS) ? $q->getString('sort') : 'new';
        $filter->page = max(1, min(1000, (int) $q->get('page', 1)));

        return $filter;
    }

    /** Query-Parameter der aktuellen Ansicht (ohne Standardwerte), z. B. für Links. */
    public function toQuery(): array
    {
        return array_filter([
            'q' => $this->q,
            'status' => $this->status,
            'mine' => $this->mine ? 1 : null,
            'genre' => $this->genre,
            'language' => $this->language,
            'format' => $this->format?->value,
            'owner' => $this->owner,
            'author' => $this->author,
            'bezirk' => $this->district?->value,
            'nah' => $this->near ? 1 : null,
            'sort' => 'new' !== $this->sort ? $this->sort : null,
        ], static fn ($v) => null !== $v && '' !== $v);
    }

    /** Liefert die Query mit einem geänderten (oder bei null entfernten) Parameter. */
    public function with(string $key, mixed $value): array
    {
        $query = $this->toQuery();
        if (null === $value || (isset($query[$key]) && (string) $query[$key] === (string) $value)) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }

        return $query;
    }

    public function hasActiveFilters(): bool
    {
        return '' !== $this->q || null !== $this->status || $this->mine || null !== $this->genre
            || null !== $this->language || null !== $this->format || null !== $this->owner || null !== $this->author
            || null !== $this->district || $this->near;
    }

    /** Anzahl der Filter aus dem „Mehr Filter“-Bereich (für die Chip-Beschriftung). */
    public function countDetailFilters(): int
    {
        return \count(array_filter([$this->genre, $this->language, $this->format, $this->owner, $this->author, $this->district]));
    }

    private static function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
