<?php

namespace App\Entity;

use App\Enum\BookCondition;
use App\Enum\BookFormat;
use App\Enum\District;
use App\Enum\LoanRequestStatus;
use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_book_title', fields: ['title'])]
#[ORM\Index(name: 'idx_book_isbn', fields: ['isbn'])]
#[ORM\Index(name: 'idx_book_created', fields: ['createdAt'])]
class Book
{
    /** So viele Bücher darf jedes Mitglied einstellen */
    public const MAX_PER_OWNER = 20;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'book.title.not_blank')]
    #[Assert\Length(max: 255, maxMessage: 'book.title.too_long')]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'book.subtitle.too_long')]
    private ?string $subtitle = null;

    /** @var Collection<int, Author> */
    #[ORM\ManyToMany(targetEntity: Author::class)]
    #[ORM\JoinTable(name: 'book_author')]
    #[Assert\Count(min: 1, minMessage: 'book.authors.min')]
    private Collection $authors;

    /** Foto der Buchrückseite (optional), ebenfalls in public/uploads/covers */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $backImage = null;

    /** Dateiname des Covers in public/uploads/covers (null = Platzhalter). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    #[ORM\Column(length: 13, nullable: true)]
    #[Assert\Isbn(message: 'book.isbn.invalid')]
    private ?string $isbn = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150, maxMessage: 'book.publisher.too_long')]
    private ?string $publisher = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(notInRangeMessage: 'book.year.range', min: 1450, max: 2100)]
    private ?int $publishedYear = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $language = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(notInRangeMessage: 'book.pages.range', min: 1, max: 20000)]
    private ?int $pageCount = null;

    #[ORM\Column(length: 20, nullable: true, enumType: BookFormat::class)]
    private ?BookFormat $format = null;

    /** @var Collection<int, Genre> */
    #[ORM\ManyToMany(targetEntity: Genre::class)]
    #[ORM\JoinTable(name: 'book_genre')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $genres;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150, maxMessage: 'book.series.too_long')]
    private ?string $series = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(notInRangeMessage: 'book.series_number.range', min: 1, max: 999)]
    private ?int $seriesNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 10000, maxMessage: 'book.description.too_long')]
    private ?string $description = null;

    // „condition“ ist in MySQL ein reserviertes Wort – daher eigener Spaltenname.
    #[ORM\Column(name: 'book_condition', length: 20, nullable: true, enumType: BookCondition::class)]
    private ?BookCondition $condition = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'book.note.too_long')]
    private ?string $ownerNote = null;

    /** Preise und Auszeichnungen, z. B. „Prix Goncourt 2016“ */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'book.awards.too_long')]
    private ?string $awards = null;

    /** Persönlicher Kommentar / Kurzrezension des Eigentümers – für alle sichtbar */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000, maxMessage: 'book.review.too_long')]
    private ?string $review = null;

    /** Eigene Bewertung, 1 bis 5 Sterne */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(notInRangeMessage: 'book.rating.range', min: 1, max: 5)]
    private ?int $rating = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Loan> */
    #[ORM\OneToMany(targetEntity: Loan::class, mappedBy: 'book', orphanRemoval: true)]
    #[ORM\OrderBy(['lentAt' => 'DESC'])]
    private Collection $loans;

    /** @var Collection<int, WaitlistEntry> */
    #[ORM\OneToMany(targetEntity: WaitlistEntry::class, mappedBy: 'book', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $waitlist;

    /** @var Collection<int, LoanRequest> */
    #[ORM\OneToMany(targetEntity: LoanRequest::class, mappedBy: 'book', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $loanRequests;

    public function __construct()
    {
        $this->authors = new ArrayCollection();
        $this->genres = new ArrayCollection();
        $this->loans = new ArrayCollection();
        $this->loanRequests = new ArrayCollection();
        $this->waitlist = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ---------- Fachliche Hilfsmethoden ----------

    /** Die laufende Ausleihe (returnedAt = null) oder null, wenn das Buch verfügbar ist. */
    public function getActiveLoan(): ?Loan
    {
        foreach ($this->loans as $loan) {
            if ($loan->isActive()) {
                return $loan;
            }
        }

        return null;
    }

    /** Wer hat das Buch gerade in der Hand? Die ausleihende Person – oder der Eigentümer. */
    public function getHolder(): ?User
    {
        $loan = $this->getActiveLoan();

        // Reserviert, aber noch nicht abgeholt → liegt noch beim Eigentümer
        return null !== $loan && $loan->isHandedOver() ? $loan->getBorrower() : $this->owner;
    }

    /** Kölner Bezirk, in dem sich das Buch gerade befindet */
    public function getLocationDistrict(): ?District
    {
        return $this->getHolder()?->getDistrict();
    }

    /**
     * „available“ = Abholung bei Eigentümer, „reserved“ = für jemanden reserviert (Übergabe steht aus),
     * „lent“ = aktuell ausgeliehen,
     * „due“ = zur Rückgabe fällig (Termin in wenigen Tagen oder schon vorbei)
     */
    public function getCirculationStatus(): string
    {
        $loan = $this->getActiveLoan();

        return match (true) {
            null === $loan => 'available',
            !$loan->isHandedOver() => 'reserved',
            $loan->isReturnDue() => 'due',
            default => 'lent',
        };
    }

    /** @return Collection<int, WaitlistEntry> */
    public function getWaitlist(): Collection
    {
        return $this->waitlist;
    }

    public function getWaitlistEntryOf(?User $user): ?WaitlistEntry
    {
        foreach ($this->waitlist as $entry) {
            if (null !== $user && $entry->getUser()->getId() === $user->getId()) {
                return $entry;
            }
        }

        return null;
    }

    /** Platz auf der Warteliste (1 = als Nächstes dran) oder null */
    public function getWaitlistPosition(?User $user): ?int
    {
        $position = 0;
        foreach ($this->waitlist as $entry) {
            ++$position;
            if (null !== $user && $entry->getUser()->getId() === $user->getId()) {
                return $position;
            }
        }

        return null;
    }

    public function isAvailable(): bool
    {
        return null === $this->getActiveLoan();
    }

    public function isOwnedBy(?User $user): bool
    {
        return null !== $user && null !== $this->owner && $this->owner->getId() === $user->getId();
    }

    /** @return list<LoanRequest> */
    public function getOpenRequests(): array
    {
        return array_values($this->loanRequests->filter(
            static fn (LoanRequest $r) => LoanRequestStatus::Open === $r->getStatus()
        )->toArray());
    }

    public function getOpenRequestBy(User $user): ?LoanRequest
    {
        foreach ($this->getOpenRequests() as $request) {
            if ($request->getRequester()->getId() === $user->getId()) {
                return $request;
            }
        }

        return null;
    }

    public function getAuthorNames(): string
    {
        return implode(', ', $this->authors->map(static fn (Author $a) => $a->getName())->toArray());
    }

    // ---------- Getter & Setter ----------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = null === $title ? null : trim($title);

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = self::nullIfEmpty($subtitle);

        return $this;
    }

    /** @return Collection<int, Author> */
    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function addAuthor(Author $author): static
    {
        if (!$this->authors->contains($author)) {
            $this->authors->add($author);
        }

        return $this;
    }

    public function removeAuthor(Author $author): static
    {
        $this->authors->removeElement($author);

        return $this;
    }

    /** @param iterable<Author> $authors */
    public function setAuthors(iterable $authors): static
    {
        $this->authors->clear();
        foreach ($authors as $author) {
            $this->addAuthor($author);
        }

        return $this;
    }

    public function getCoverImage(): ?string
    {
        return $this->coverImage;
    }

    public function getBackImage(): ?string
    {
        return $this->backImage;
    }

    public function setBackImage(?string $backImage): static
    {
        $this->backImage = $backImage;

        return $this;
    }

    public function setCoverImage(?string $coverImage): static
    {
        $this->coverImage = $coverImage;

        return $this;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    /** Speichert die ISBN ohne Bindestriche/Leerzeichen. */
    public function setIsbn(?string $isbn): static
    {
        $normalized = null === $isbn ? '' : strtoupper((string) preg_replace('/[^0-9Xx]/', '', $isbn));
        $this->isbn = '' === $normalized ? null : $normalized;

        return $this;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setPublisher(?string $publisher): static
    {
        $this->publisher = self::nullIfEmpty($publisher);

        return $this;
    }

    public function getPublishedYear(): ?int
    {
        return $this->publishedYear;
    }

    public function setPublishedYear(?int $publishedYear): static
    {
        $this->publishedYear = $publishedYear;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = self::nullIfEmpty($language);

        return $this;
    }

    public function getPageCount(): ?int
    {
        return $this->pageCount;
    }

    public function setPageCount(?int $pageCount): static
    {
        $this->pageCount = $pageCount;

        return $this;
    }

    public function getFormat(): ?BookFormat
    {
        return $this->format;
    }

    public function setFormat(?BookFormat $format): static
    {
        $this->format = $format;

        return $this;
    }

    /** @return Collection<int, Genre> */
    public function getGenres(): Collection
    {
        return $this->genres;
    }

    public function addGenre(Genre $genre): static
    {
        if (!$this->genres->contains($genre)) {
            $this->genres->add($genre);
        }

        return $this;
    }

    public function removeGenre(Genre $genre): static
    {
        $this->genres->removeElement($genre);

        return $this;
    }

    public function getSeries(): ?string
    {
        return $this->series;
    }

    public function setSeries(?string $series): static
    {
        $this->series = self::nullIfEmpty($series);

        return $this;
    }

    public function getSeriesNumber(): ?int
    {
        return $this->seriesNumber;
    }

    public function setSeriesNumber(?int $seriesNumber): static
    {
        $this->seriesNumber = $seriesNumber;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = self::nullIfEmpty($description);

        return $this;
    }

    public function getCondition(): ?BookCondition
    {
        return $this->condition;
    }

    public function setCondition(?BookCondition $condition): static
    {
        $this->condition = $condition;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getAwards(): ?string
    {
        return $this->awards;
    }

    public function setAwards(?string $awards): static
    {
        $this->awards = self::nullIfEmpty($awards);

        return $this;
    }

    public function getReview(): ?string
    {
        return $this->review;
    }

    public function setReview(?string $review): static
    {
        $this->review = self::nullIfEmpty($review);

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getOwnerNote(): ?string
    {
        return $this->ownerNote;
    }

    public function setOwnerNote(?string $ownerNote): static
    {
        $this->ownerNote = self::nullIfEmpty($ownerNote);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, Loan> */
    public function getLoans(): Collection
    {
        return $this->loans;
    }

    public function addLoan(Loan $loan): static
    {
        if (!$this->loans->contains($loan)) {
            $this->loans->add($loan);
        }

        return $this;
    }

    /** @return Collection<int, LoanRequest> */
    public function getLoanRequests(): Collection
    {
        return $this->loanRequests;
    }

    public function addLoanRequest(LoanRequest $request): static
    {
        if (!$this->loanRequests->contains($request)) {
            $this->loanRequests->add($request);
        }

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }
}
