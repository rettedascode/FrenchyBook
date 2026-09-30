<?php

namespace App\Entity;

use App\Enum\LoanRequestStatus;
use App\Repository\LoanRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanRequestRepository::class)]
#[ORM\Index(name: 'idx_request_status', fields: ['status'])]
class LoanRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'loanRequests')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Book $book;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $requester;

    #[ORM\Column(length: 20, enumType: LoanRequestStatus::class)]
    private LoanRequestStatus $status = LoanRequestStatus::Open;

    #[ORM\Column(length: 300, nullable: true)]
    #[Assert\Length(max: 300, maxMessage: 'request.message.too_long')]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    public function __construct(Book $book, User $requester, ?string $message = null)
    {
        $this->book = $book;
        $this->requester = $requester;
        $this->setMessage($message);
        $this->createdAt = new \DateTimeImmutable();
        $book->addLoanRequest($this);
    }

    public function isOpen(): bool
    {
        return LoanRequestStatus::Open === $this->status;
    }

    public function accept(): void
    {
        $this->status = LoanRequestStatus::Accepted;
        $this->respondedAt = new \DateTimeImmutable();
    }

    public function decline(): void
    {
        $this->status = LoanRequestStatus::Declined;
        $this->respondedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): Book
    {
        return $this->book;
    }

    public function getRequester(): User
    {
        return $this->requester;
    }

    public function getStatus(): LoanRequestStatus
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $message = null === $message ? null : trim($message);
        $this->message = '' === $message ? null : $message;

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

    public function getRespondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }
}
