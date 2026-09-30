<?php

namespace App\Entity;

use App\Enum\District;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_user_name', fields: ['name'])]
#[UniqueEntity(fields: ['email'], message: 'user.email.taken')]
// Benutzername ist eindeutig – unabhängig von Groß-/Kleinschreibung („lena“ = „Lena“)
#[UniqueEntity(fields: ['name'], message: 'user.name.taken', repositoryMethod: 'findByNameInsensitive')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const LOCALES = ['de', 'fr'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'user.email.not_blank')]
    #[Assert\Email(message: 'user.email.invalid')]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    /** Benutzername zum Anmelden und zugleich der Name, unter dem die Freunde einen sehen. */
    #[ORM\Column(length: 60)]
    #[Assert\NotBlank(message: 'user.name.not_blank')]
    #[Assert\Length(min: 2, max: 30, minMessage: 'user.name.too_short', maxMessage: 'user.name.too_long')]
    #[Assert\Regex(pattern: '/^[\p{L}\p{N}][\p{L}\p{N} ._-]*$/u', message: 'user.name.invalid_chars')]
    private ?string $name = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** Sprache der Oberfläche und der E-Mails an diese Person („de“ oder „fr“). */
    #[ORM\Column(length: 5, options: ['default' => 'fr'])]
    #[Assert\Choice(choices: self::LOCALES, message: 'user.locale.invalid')]
    private string $locale = 'fr';

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'user.first_name.not_blank')]
    #[Assert\Length(max: 50, maxMessage: 'user.first_name.too_long')]
    private ?string $firstName = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'user.last_name.not_blank')]
    #[Assert\Length(max: 50, maxMessage: 'user.last_name.too_long')]
    private ?string $lastName = null;

    /** Kölner Stadtbezirk – ungefährer Abholort der eigenen Bücher */
    #[ORM\Column(length: 20, nullable: true, enumType: District::class)]
    #[Assert\NotNull(message: 'user.district.not_blank')]
    private ?District $district = null;

    /** Letzte Anmeldung (auch automatisch über „Angemeldet bleiben“) – für die Verwaltung. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return $this;
    }

    /** Anfangsbuchstabe für den Avatar. */
    public function getInitial(): string
    {
        return mb_strtoupper(mb_substr($this->name ?? '?', 0, 1));
    }

    /** Angemeldet wird mit dem Benutzernamen (nicht mit der E-Mail). */
    public function getUserIdentifier(): string
    {
        return (string) $this->name;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = null !== $firstName ? (trim($firstName) ?: null) : null;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = null !== $lastName ? (trim($lastName) ?: null) : null;

        return $this;
    }

    /** „Vorname Nachname“ – oder der Benutzername, solange die Namen fehlen */
    public function getFullName(): string
    {
        $full = trim($this->firstName.' '.$this->lastName);

        return '' !== $full ? $full : (string) $this->name;
    }

    public function getDistrict(): ?District
    {
        return $this->district;
    }

    public function setDistrict(?District $district): static
    {
        $this->district = $district;

        return $this;
    }

    /** Fehlen Angaben aus der Registrierung (ältere Konten)? */
    public function isProfileIncomplete(): bool
    {
        return null === $this->firstName || null === $this->lastName || null === $this->district;
    }

    public function isAdmin(): bool
    {
        return \in_array('ROLE_ADMIN', $this->roles, true);
    }

    public function setAdmin(bool $admin): static
    {
        $roles = array_values(array_diff($this->roles, ['ROLE_ADMIN']));
        if ($admin) {
            $roles[] = 'ROLE_ADMIN';
        }
        $this->roles = $roles;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = \in_array($locale, self::LOCALES, true) ? $locale : 'de';

        return $this;
    }

    /** Verhindert, dass der Passwort-Hash in der Session landet. */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', (string) $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
