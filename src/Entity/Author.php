<?php

namespace App\Entity;

use App\Repository\AuthorRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthorRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_author_name', fields: ['name'])]
class Author
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name;

    public function __construct(string $name)
    {
        $this->name = self::normalizeName($name);
    }

    public static function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = self::normalizeName($name);

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
