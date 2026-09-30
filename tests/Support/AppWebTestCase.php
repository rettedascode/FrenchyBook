<?php

namespace App\Tests\Support;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Loan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AppWebTestCase extends WebTestCase
{
    protected const PASSWORD = 'geheim123';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(static::getContainer()->getParameter('app.covers_dir'));
        parent::tearDown();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createUser(string $name, ?string $email = null): User
    {
        $user = (new User())->setName($name)->setEmail($email ?? strtolower($name).'@example.com');
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    protected function createBook(User $owner, string $title = 'Der Schwarm', string $author = 'Frank Schätzing', array $extra = []): Book
    {
        $book = (new Book())->setOwner($owner)->setTitle($title);
        $existing = $this->em()->getRepository(Author::class)->findOneBy(['name' => $author]);
        $book->addAuthor($existing ?? new Author($author));
        foreach ($book->getAuthors() as $a) {
            $this->em()->persist($a);
        }
        foreach ($extra as $setter => $value) {
            $book->{'set'.ucfirst($setter)}($value);
        }
        $this->em()->persist($book);
        $this->em()->flush();

        return $book;
    }

    protected function lend(Book $book, User $borrower, ?string $dueAt = null): Loan
    {
        $loan = (new Loan($book))->setBorrower($borrower)->setDueAt($dueAt ? new \DateTimeImmutable($dueAt) : null);
        $this->em()->persist($loan);
        $this->em()->flush();

        return $loan;
    }

    /** Lädt ein Entity frisch aus der Datenbank (nach Requests des Clients). */
    protected function reload(object $entity): object
    {
        $this->em()->clear();

        return $this->em()->find($entity::class, $entity->getId());
    }
}
