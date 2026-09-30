<?php

namespace App\Entity;

use App\Repository\LoanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Eine Ausleihe. returnedAt = null bedeutet: Das Buch ist noch verliehen.
 * Pro Buch darf es höchstens eine aktive Ausleihe geben (siehe LoanManager).
 */
#[ORM\Entity(repositoryClass: LoanRepository::class)]
#[ORM\Index(name: 'idx_loan_active', fields: ['book', 'returnedAt'])]
#[ORM\Index(name: 'idx_loan_due', fields: ['returnedAt', 'dueAt'])]
class Loan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'loans')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Book $book;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'loan.borrower.not_null')]
    private ?User $borrower = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lentAt;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual('today', message: 'loan.due_at.past', groups: ['create'])]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $returnedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'loan.note.too_long')]
    private ?string $note = null;

    /** Wann zuletzt eine Erinnerungs-Mail verschickt wurde (verhindert tägliche Mails). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    /** Erinnerung „bald fällig“ (einmal, kurz vor dem Rückgabetermin) */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueSoonReminderSentAt = null;

    /** Wann das Buch wirklich übergeben wurde (null = reserviert, Abholung steht noch aus) */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $handedOverAt = null;

    /** Wie oft die ausleihende Person selbst verlängert hat */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $extensionCount = 0;

    public function __construct(Book $book)
    {
        $this->book = $book;
        $this->lentAt = new \DateTimeImmutable();
        $book->addLoan($this);
    }

    /** Um so viele Tage verlängert man selbst – höchstens MAX_EXTENSIONS Mal */
    public const EXTENSION_DAYS = 14;
    public const MAX_EXTENSIONS = 2;
    /** Ab so vielen Tagen vor dem Termin gilt ein Buch als „zur Rückgabe fällig“ */
    public const DUE_SOON_DAYS = 3;

    public function isHandedOver(): bool
    {
        return null !== $this->handedOverAt;
    }

    public function getHandedOverAt(): ?\DateTimeImmutable
    {
        return $this->handedOverAt;
    }

    /**
     * Übergabe bestätigen: Die Ausleihe beginnt jetzt. Ein vereinbarter Rückgabetermin
     * verschiebt sich um die Tage, die bis zur Abholung vergangen sind – die Leihfrist bleibt gleich.
     */
    public function confirmHandover(): static
    {
        $now = new \DateTimeImmutable();
        if (null !== $this->dueAt) {
            $waited = (int) $this->lentAt->setTime(0, 0)->diff($now->setTime(0, 0))->days;
            if ($waited > 0) {
                $this->dueAt = $this->dueAt->modify(sprintf('+%d days', $waited));
            }
        }
        $this->lentAt = $now;
        $this->handedOverAt = $now;

        return $this;
    }

    /** Direkt übergeben (z. B. wenn der Eigentümer das Buch persönlich weitergibt und gleich einträgt) */
    public function markHandedOver(): static
    {
        $this->handedOverAt = $this->lentAt;

        return $this;
    }

    /** Rückgabetermin in wenigen Tagen oder schon vorbei */
    public function isReturnDue(?\DateTimeImmutable $today = null): bool
    {
        if (!$this->isActive() || !$this->isHandedOver() || null === $this->dueAt) {
            return false;
        }
        $today ??= new \DateTimeImmutable('today');

        return $this->dueAt <= $today->modify(sprintf('+%d days', self::DUE_SOON_DAYS));
    }

    public function getExtensionCount(): int
    {
        return $this->extensionCount;
    }

    public function canBeExtended(): bool
    {
        return $this->isActive() && $this->isHandedOver() && $this->extensionCount < self::MAX_EXTENSIONS;
    }

    /** Neuer Termin: vom bisherigen Termin (oder heute, falls der schon vorbei ist) + 2 Wochen */
    public function extend(): static
    {
        $today = new \DateTimeImmutable('today');
        $base = null !== $this->dueAt && $this->dueAt > $today ? $this->dueAt : $today;
        $this->dueAt = $base->modify(sprintf('+%d days', self::EXTENSION_DAYS));
        ++$this->extensionCount;
        $this->reminderSentAt = null;
        $this->dueSoonReminderSentAt = null;

        return $this;
    }

    public function getDueSoonReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->dueSoonReminderSentAt;
    }

    public function setDueSoonReminderSentAt(?\DateTimeImmutable $at): static
    {
        $this->dueSoonReminderSentAt = $at;

        return $this;
    }

    public function isActive(): bool
    {
        return null === $this->returnedAt;
    }

    public function isOverdue(?\DateTimeImmutable $today = null): bool
    {
        if (!$this->isHandedOver()) {
            return false;
        }
        if (!$this->isActive() || null === $this->dueAt) {
            return false;
        }
        $today ??= new \DateTimeImmutable('today');

        return $this->dueAt < $today->setTime(0, 0);
    }

    public function getDaysOverdue(?\DateTimeImmutable $today = null): int
    {
        if (!$this->isOverdue($today)) {
            return 0;
        }
        $today ??= new \DateTimeImmutable('today');

        return (int) $this->dueAt->diff($today->setTime(0, 0))->days;
    }

    public function markReturned(): void
    {
        $this->returnedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): Book
    {
        return $this->book;
    }

    public function getBorrower(): ?User
    {
        return $this->borrower;
    }

    public function setBorrower(?User $borrower): static
    {
        $this->borrower = $borrower;

        return $this;
    }

    public function getLentAt(): \DateTimeImmutable
    {
        return $this->lentAt;
    }

    public function setLentAt(\DateTimeImmutable $lentAt): static
    {
        $this->lentAt = $lentAt;

        return $this;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(?\DateTimeImmutable $dueAt): static
    {
        $this->dueAt = $dueAt;

        return $this;
    }

    public function getReturnedAt(): ?\DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function setReturnedAt(?\DateTimeImmutable $returnedAt): static
    {
        $this->returnedAt = $returnedAt;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $note = null === $note ? null : trim($note);
        $this->note = '' === $note ? null : $note;

        return $this;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function setReminderSentAt(?\DateTimeImmutable $reminderSentAt): static
    {
        $this->reminderSentAt = $reminderSentAt;

        return $this;
    }
}
