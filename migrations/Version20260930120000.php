<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Übergabe-Bestätigung: Eine Ausleihe beginnt erst, wenn das Buch wirklich übergeben ist.
 * Bestehende Ausleihen gelten als bereits übergeben.
 */
final class Version20260930120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'loan.handed_over_at (Übergabe bestätigt)';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('loan')->addColumn('handed_over_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
    }

    public function postUp(Schema $schema): void
    {
        $this->connection->executeStatement('UPDATE loan SET handed_over_at = lent_at WHERE handed_over_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('loan')->dropColumn('handed_over_at');
    }
}
