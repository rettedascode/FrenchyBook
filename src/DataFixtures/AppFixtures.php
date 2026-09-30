<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Genre;
use App\Entity\Loan;
use App\Entity\LoanRequest;
use App\Entity\User;
use App\Enum\BookCondition;
use App\Enum\BookFormat;
use App\Enum\District;
use App\Service\CoverManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Beispieldaten zum Ausprobieren:
 * 5 Nutzer (Passwort jeweils „frenchy123“), 15 Bücher mit Covern, Ausleihen
 * (eine davon überfällig), Verlauf und offene Anfragen.
 *
 * Cover werden – wenn Internet da ist – von Open Library geladen, sonst lokal erzeugt.
 * Offline erzwingen: FIXTURES_OFFLINE=1 php bin/console doctrine:fixtures:load
 */
class AppFixtures extends Fixture
{
    public const PASSWORD = 'frenchy123';

    /** Gleiche Auswahl wie in der Migration „Standard-Genres anlegen“. */
    private const GENRES = [
        'Biografie', 'Fantasy', 'Geschichte', 'Horror', 'Humor', 'Jugendbuch', 'Kinderbuch',
        'Klassiker', 'Kochbuch', 'Krimi', 'Liebesroman', 'Lyrik', 'Ratgeber', 'Reise', 'Roman',
        'Sachbuch', 'Science-Fiction', 'Thriller',
    ];

    private bool $online;

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly CoverManager $covers,
        #[Autowire('%app.covers_dir%')] private readonly string $coversDir,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%kernel.environment%')] string $environment,
    ) {
        $this->online = 'test' !== $environment && !filter_var($_SERVER['FIXTURES_OFFLINE'] ?? getenv('FIXTURES_OFFLINE'), \FILTER_VALIDATE_BOOL);
    }

    public function load(ObjectManager $manager): void
    {
        $this->cleanCoverFiles();

        // ---------- Nutzer ----------
        $users = [];
        // Sophie nutzt die App auf Französisch
        foreach ([
            'jeremy' => ['Jeremy', 'jeremy@example.com', ['ROLE_ADMIN'], 'de', 'Thelen', District::Ehrenfeld],
            'lena' => ['Lena', 'lena@example.com', [], 'de', 'Schmitz', District::Nippes],
            'max' => ['Max', 'max@example.com', [], 'de', 'Becker', District::Innenstadt],
            'sophie' => ['Sophie', 'sophie@example.com', [], 'fr', 'Martin', District::Lindenthal],
            'tom' => ['Tom', 'tom@example.com', [], 'de', 'Wagner', District::Kalk],
        ] as $key => [$name, $email, $roles, $locale, $lastName, $district]) {
            $user = (new User())->setName($name)->setEmail($email)->setRoles($roles)->setLocale($locale)
                ->setFirstName($name)->setLastName($lastName)->setDistrict($district);
            $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
            $manager->persist($user);
            $users[$key] = $user;
        }

        // ---------- Genres ----------
        $genres = [];
        foreach (self::GENRES as $name) {
            $genres[$name] = new Genre($name);
            $manager->persist($genres[$name]);
        }

        // ---------- Bücher ----------
        $authors = [];
        $books = [];
        foreach (self::bookData() as $key => $data) {
            $book = (new Book())
                ->setOwner($users[$data['owner']])
                ->setTitle($data['title'])
                ->setSubtitle($data['subtitle'] ?? null)
                ->setIsbn($data['isbn'] ?? null)
                ->setPublisher($data['publisher'] ?? null)
                ->setPublishedYear($data['year'] ?? null)
                ->setLanguage($data['language'] ?? null)
                ->setPageCount($data['pages'] ?? null)
                ->setFormat($data['format'] ?? null)
                ->setCondition($data['condition'] ?? null)
                ->setSeries($data['series'][0] ?? null)
                ->setSeriesNumber($data['series'][1] ?? null)
                ->setDescription($data['description'] ?? null)
                ->setOwnerNote($data['note'] ?? null)
                ->setCreatedAt(new \DateTimeImmutable(sprintf('-%d days', $data['addedDaysAgo'])));

            foreach ($data['authors'] as $name) {
                $authors[$name] ??= new Author($name);
                $manager->persist($authors[$name]);
                $book->addAuthor($authors[$name]);
            }
            foreach ($data['genres'] as $genre) {
                $book->addGenre($genres[$genre]);
            }
            if (!($data['noCover'] ?? false)) {
                $book->setCoverImage($this->cover($book, $data['color']));
            }

            $manager->persist($book);
            $books[$key] = $book;
        }

        // ---------- Ausleihen (aktuell & Verlauf) ----------
        $loan = static fn (Book $book, User $borrower, int $lentDaysAgo, ?int $dueInDays = null, ?int $returnedDaysAgo = null, ?string $note = null): Loan => (new Loan($book))
            ->setBorrower($borrower)
            ->setLentAt(new \DateTimeImmutable(sprintf('-%d days 18:30', $lentDaysAgo)))
            ->setDueAt(null === $dueInDays ? null : new \DateTimeImmutable(sprintf('today %+d days', $dueInDays)))
            ->setReturnedAt(null === $returnedDaysAgo ? null : new \DateTimeImmutable(sprintf('-%d days 12:00', $returnedDaysAgo)))
            ->setNote($note);

        foreach ([
            // aktiv
            $loan($books['potter'], $users['jeremy'], 20, 8),
            $loan($books['panem1'], $users['sophie'], 40, -10, null, 'Beim Spieleabend mitgegeben'),   // überfällig
            $loan($books['schwarm'], $users['lena'], 5),
            $loan($books['prince'], $users['max'], 3, 14),
            // Verlauf
            $loan($books['rose'], $users['jeremy'], 120, 90, 95),
            $loan($books['rose'], $users['tom'], 60, 30, 35),
            $loan($books['tschick'], $users['max'], 80, null, 50),
            $loan($books['sapiens'], $users['lena'], 150, 120, 110),
            $loan($books['potter'], $users['sophie'], 200, null, 170),
        ] as $l) {
            $manager->persist($l);
        }

        // ---------- Anfragen ----------
        $requests = [
            [new LoanRequest($books['etranger'], $users['lena'], 'Ich will mein Französisch auffrischen 😊'), 2],
            [new LoanRequest($books['sapiens'], $users['tom']), 1],
            [new LoanRequest($books['hailmary'], $users['jeremy'], 'Klingt super – hättest du es nächste Woche übrig?'), 0],
            [new LoanRequest($books['kaenguru'], $users['sophie']), 4],
        ];
        foreach ($requests as [$request, $daysAgo]) {
            $request->setCreatedAt(new \DateTimeImmutable(sprintf('-%d days', $daysAgo)));
            $manager->persist($request);
        }
        $declined = new LoanRequest($books['ottolenghi'], $users['max'], 'Für das Grillfest?');
        $declined->decline();
        $manager->persist($declined);

        $manager->flush();
    }

    private function cover(Book $book, string $color): ?string
    {
        if ($this->online && null !== $book->getIsbn()) {
            $file = $this->covers->storeFromUrl(sprintf('https://covers.openlibrary.org/b/isbn/%s-L.jpg?default=false', $book->getIsbn()));
            if (null !== $file) {
                return $file;
            }
        }

        return $this->covers->storeBinary(
            FixtureCoverGenerator::generate((string) $book->getTitle(), $book->getAuthorNames(), $color),
            'image/jpeg',
        );
    }

    private function cleanCoverFiles(): void
    {
        $fs = new Filesystem();
        if (is_dir($this->coversDir)) {
            $fs->remove(glob($this->coversDir.'/*.{webp,jpg,jpeg,png}', \GLOB_BRACE) ?: []);
        }
        $fs->remove($this->projectDir.'/public/media/cache');
    }

    /** @return array<string, array<string, mixed>> */
    private static function bookData(): array
    {
        return [
            'potter' => [
                'owner' => 'lena', 'title' => 'Harry Potter und der Stein der Weisen', 'authors' => ['J. K. Rowling'],
                'isbn' => '9783551551672', 'publisher' => 'Carlsen', 'year' => 1998, 'language' => 'de', 'pages' => 335,
                'format' => BookFormat::Hardcover, 'condition' => BookCondition::Used, 'genres' => ['Fantasy', 'Jugendbuch'],
                'series' => ['Harry Potter', 1], 'color' => '#8a3b5c', 'addedDaysAgo' => 300,
                'description' => 'Harry erfährt an seinem elften Geburtstag, dass er ein Zauberer ist – und plötzlich steht ihm die Welt von Hogwarts offen.',
                'note' => 'Erstausgabe – bitte nicht in die Badewanne mitnehmen.',
            ],
            'panem1' => [
                'owner' => 'max', 'title' => 'Die Tribute von Panem', 'subtitle' => 'Tödliche Spiele', 'authors' => ['Suzanne Collins'],
                'isbn' => '9783789132186', 'publisher' => 'Oetinger', 'year' => 2009, 'language' => 'de', 'pages' => 414,
                'format' => BookFormat::Hardcover, 'condition' => BookCondition::Good, 'genres' => ['Science-Fiction', 'Jugendbuch'],
                'series' => ['Die Tribute von Panem', 1], 'color' => '#9a4a1c', 'addedDaysAgo' => 250,
                'description' => 'Katniss meldet sich freiwillig für die grausamen Hungerspiele, um ihre kleine Schwester zu retten.',
            ],
            'panem2' => [
                'owner' => 'max', 'title' => 'Die Tribute von Panem', 'subtitle' => 'Gefährliche Liebe', 'authors' => ['Suzanne Collins'],
                'isbn' => '9783789132193', 'publisher' => 'Oetinger', 'year' => 2010, 'language' => 'de', 'pages' => 432,
                'format' => BookFormat::Hardcover, 'condition' => BookCondition::Good, 'genres' => ['Science-Fiction', 'Jugendbuch'],
                'series' => ['Die Tribute von Panem', 2], 'color' => '#4b3f8f', 'addedDaysAgo' => 249,
            ],
            'rose' => [
                'owner' => 'sophie', 'title' => 'Der Name der Rose', 'authors' => ['Umberto Eco'],
                'isbn' => '9783423105514', 'publisher' => 'dtv', 'year' => 1986, 'language' => 'de', 'pages' => 656,
                'format' => BookFormat::Paperback, 'condition' => BookCondition::Used, 'genres' => ['Krimi', 'Klassiker'],
                'color' => '#5a5f69', 'addedDaysAgo' => 400,
                'description' => 'Ein Kloster im Jahr 1327, eine Reihe rätselhafter Todesfälle und ein Mönch, der der Logik mehr traut als dem Aberglauben.',
            ],
            'prince' => [
                'owner' => 'jeremy', 'title' => 'Le Petit Prince', 'authors' => ['Antoine de Saint-Exupéry'],
                'isbn' => '9782070612758', 'publisher' => 'Gallimard', 'year' => 2007, 'language' => 'fr', 'pages' => 96,
                'format' => BookFormat::Paperback, 'condition' => BookCondition::Good, 'genres' => ['Klassiker', 'Kinderbuch'],
                'color' => '#1f6474', 'addedDaysAgo' => 90,
            ],
            'sapiens' => [
                'owner' => 'jeremy', 'title' => 'Sapiens', 'subtitle' => 'A Brief History of Humankind', 'authors' => ['Yuval Noah Harari'],
                'isbn' => '9780099590088', 'publisher' => 'Vintage', 'year' => 2015, 'language' => 'en', 'pages' => 512,
                'format' => BookFormat::Paperback, 'condition' => BookCondition::Good, 'genres' => ['Sachbuch', 'Geschichte'],
                'color' => '#7a5a12', 'addedDaysAgo' => 180,
            ],
            'schwarm' => [
                'owner' => 'tom', 'title' => 'Der Schwarm', 'authors' => ['Frank Schätzing'],
                'isbn' => '9783596164530', 'publisher' => 'Fischer', 'year' => 2005, 'language' => 'de', 'pages' => 998,
                'format' => BookFormat::Paperback, 'condition' => BookCondition::Used, 'genres' => ['Thriller', 'Science-Fiction'],
                'color' => '#1f6474', 'addedDaysAgo' => 60,
            ],
            'tschick' => [
                'owner' => 'lena', 'title' => 'Tschick', 'authors' => ['Wolfgang Herrndorf'],
                'isbn' => '9783499256356', 'publisher' => 'rororo', 'year' => 2012, 'language' => 'de', 'pages' => 256,
                'format' => BookFormat::Paperback, 'condition' => BookCondition::Good, 'genres' => ['Jugendbuch', 'Roman'],
                'color' => '#2f6b4f', 'addedDaysAgo' => 150,
            ],
            'asterix' => [
                'owner' => 'tom', 'title' => 'Asterix der Gallier', 'authors' => ['René Goscinny', 'Albert Uderzo'],
                'isbn' => '9783770436002', 'publisher' => 'Egmont', 'year' => 2013, 'language' => 'de', 'pages' => 48,
                'format' => BookFormat::Comic, 'condition' => BookCondition::Good, 'genres' => ['Humor'],
                'series' => ['Asterix', 1], 'color' => '#9a4a1c', 'addedDaysAgo' => 30,
            ],
            'cafe' => [
                'owner' => 'sophie', 'title' => 'Das Café am Rande der Welt', 'authors' => ['John Strelecky'],
                'isbn' => '9783423209694', 'publisher' => 'dtv', 'year' => 2007, 'language' => 'de', 'pages' => 128,
                'format' => BookFormat::Paperback, 'condition' => BookCondition::New, 'genres' => ['Ratgeber'],
                'color' => '#2c55b8', 'addedDaysAgo' => 20,
            ],
            'ottolenghi' => [
                'owner' => 'lena', 'title' => 'Simple', 'subtitle' => 'Das Kochbuch', 'authors' => ['Yotam Ottolenghi'],
                'isbn' => '9783831035205', 'publisher' => 'Dorling Kindersley', 'year' => 2018, 'language' => 'de', 'pages' => 320,
                'format' => BookFormat::Hardcover, 'condition' => BookCondition::Good, 'genres' => ['Kochbuch'],
                'color' => '#2f6b4f', 'addedDaysAgo' => 12, 'note' => 'Hat schon ein paar Flecken – ist halt ein Kochbuch.',
            ],
            'etranger' => [
                'owner' => 'jeremy', 'title' => 'L’Étranger', 'authors' => ['Albert Camus'],
                'isbn' => '9782070360024', 'publisher' => 'Gallimard', 'year' => 1972, 'language' => 'fr', 'pages' => 186,
                'format' => BookFormat::Paperback, 'condition' => BookCondition::Used, 'genres' => ['Klassiker', 'Roman'],
                'color' => '#5a5f69', 'addedDaysAgo' => 8,
            ],
            'hailmary' => [
                'owner' => 'max', 'title' => 'Project Hail Mary', 'authors' => ['Andy Weir'],
                'isbn' => '9780593135204', 'publisher' => 'Ballantine', 'year' => 2021, 'language' => 'en', 'pages' => 496,
                'format' => BookFormat::Hardcover, 'condition' => BookCondition::New, 'genres' => ['Science-Fiction'],
                'color' => '#4b3f8f', 'addedDaysAgo' => 5,
            ],
            'kaenguru' => [
                'owner' => 'tom', 'title' => 'Die Känguru-Chroniken', 'subtitle' => 'Ansichten eines vorlauten Beuteltiers', 'authors' => ['Marc-Uwe Kling'],
                'isbn' => '9783548372570', 'publisher' => 'Ullstein', 'year' => 2009, 'language' => 'de', 'pages' => 272,
                'format' => BookFormat::Paperback, 'condition' => BookCondition::Used, 'genres' => ['Humor'],
                'series' => ['Känguru', 1], 'color' => '#7a5a12', 'addedDaysAgo' => 3,
            ],
            'rezepte' => [
                'owner' => 'sophie', 'title' => 'Omas Familienrezepte', 'authors' => ['Hilde Berger'],
                'language' => 'de', 'format' => BookFormat::Hardcover, 'condition' => BookCondition::Used, 'genres' => ['Kochbuch'],
                'color' => '#8a3b5c', 'addedDaysAgo' => 1, 'noCover' => true,
                'description' => 'Handgeschriebene Lieblingsrezepte, über Jahrzehnte gesammelt.',
                'note' => 'Unikat! Nur zum Anschauen bei mir zu Hause.',
            ],
        ];
    }
}
