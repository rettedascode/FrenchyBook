<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Legt eine sinnvolle Auswahl an Genres an, damit man direkt loslegen kann.
 * Weitere Genres entstehen automatisch, wenn jemand im Formular ein neues einträgt.
 */
final class Version20260928000800 extends AbstractMigration
{
    public const GENRES = [
        'Biografie', 'Fantasy', 'Geschichte', 'Horror', 'Humor', 'Jugendbuch', 'Kinderbuch',
        'Klassiker', 'Kochbuch', 'Krimi', 'Liebesroman', 'Lyrik', 'Ratgeber', 'Reise', 'Roman',
        'Sachbuch', 'Science-Fiction', 'Thriller',
    ];

    public function getDescription(): string
    {
        return 'Standard-Genres anlegen';
    }

    public function up(Schema $schema): void
    {
        foreach (self::GENRES as $name) {
            $this->addSql('INSERT INTO genre (name) VALUES (?)', [$name]);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::GENRES as $name) {
            $this->addSql('DELETE FROM genre WHERE name = ?', [$name]);
        }
    }
}
