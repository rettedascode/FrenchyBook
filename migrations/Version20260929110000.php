<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Neue Mitglieder bekommen standardmäßig Französisch (bestehende bleiben unverändert).
 */
final class Version20260929110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Standardsprache user.locale: fr';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('`user`')->modifyColumn('locale', ['default' => 'fr']);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('`user`')->modifyColumn('locale', ['default' => 'de']);
    }
}
