<?php

namespace App\Entity;

use App\Repository\PushSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ein Gerät, auf dem jemand Push-Benachrichtigungen aktiviert hat.
 * Ein Nutzer kann mehrere Geräte haben (Handy, Laptop …).
 */
#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_push_endpoint', fields: ['endpointHash'])]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** Adresse beim Push-Dienst des Browsers (Google, Apple, Mozilla …) – kann sehr lang sein. */
    #[ORM\Column(type: Types::TEXT)]
    private string $endpoint;

    /** SHA-256 des Endpoints für den eindeutigen Index. */
    #[ORM\Column(length: 64)]
    private string $endpointHash;

    #[ORM\Column(length: 255)]
    private string $publicKey;

    #[ORM\Column(length: 100)]
    private string $authToken;

    /** Kurzbeschreibung des Geräts, z. B. „Android · Chrome“. */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $deviceLabel = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSuccessAt = null;

    public function __construct(User $user, string $endpoint, string $publicKey, string $authToken)
    {
        $this->user = $user;
        $this->endpoint = $endpoint;
        $this->endpointHash = self::hash($endpoint);
        $this->publicKey = $publicKey;
        $this->authToken = $authToken;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    /** Ein Gerät kann sich neu anmelden (neue Schlüssel) oder den Besitzer wechseln. */
    public function update(User $user, string $publicKey, string $authToken, ?string $deviceLabel): void
    {
        $this->user = $user;
        $this->publicKey = $publicKey;
        $this->authToken = $authToken;
        $this->deviceLabel = $deviceLabel;
    }

    public function markSuccess(): void
    {
        $this->lastSuccessAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function getDeviceLabel(): ?string
    {
        return $this->deviceLabel;
    }

    public function setDeviceLabel(?string $deviceLabel): static
    {
        $this->deviceLabel = null === $deviceLabel ? null : mb_substr($deviceLabel, 0, 100);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSuccessAt(): ?\DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }
}
