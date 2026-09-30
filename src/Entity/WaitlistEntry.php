<?php

namespace App\Entity;

use App\Repository\WaitlistEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ein Platz auf der Warteliste für ein ausgeliehenes Buch.
 * Wer zuerst kommt, wird nach der Rückgabe zuerst benachrichtigt.
 */
#[ORM\Entity(repositoryClass: WaitlistEntryRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_waitlist_book_user', columns: ['book_id', 'user_id'])]
class WaitlistEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'waitlist')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Book $book;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** Wann die Person „Das Buch ist wieder da“ bekommen hat (null = noch nicht an der Reihe). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    public function __construct(Book $book, User $user)
    {
        $this->book = $book;
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
        $book->getWaitlist()->add($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): Book
    {
        return $this->book;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function markNotified(): static
    {
        $this->notifiedAt = new \DateTimeImmutable();

        return $this;
    }
}
