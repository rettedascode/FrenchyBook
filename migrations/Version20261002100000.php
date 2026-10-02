<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Zweites Foto pro Buch: die Rückseite (optional).
 */
final class Version20261002100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'book.back_image (Foto der Rückseite)';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('book')->addColumn('back_image', Types::STRING, ['length' => 255, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('book')->dropColumn('back_image');
    }
}
