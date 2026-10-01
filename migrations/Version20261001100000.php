<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Kölner Stadtteil je Mitglied (offizielle Nummer, z. B. 403 = Bickendorf).
 */
final class Version20261001100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'user.quarter (Kölner Stadtteil)';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('`user`')->addColumn('quarter', Types::SMALLINT, ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('`user`')->dropColumn('quarter');
    }
}
