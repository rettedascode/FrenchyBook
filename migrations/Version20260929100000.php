<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Verwaltung: letzte Anmeldung je Mitglied und Einstellungen (z. B. Einladungscode).
 */
final class Version20260929100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'user.last_login_at und Tabelle app_setting';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('`user`')->addColumn('last_login_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);

        $table = $schema->createTable('app_setting');
        $table->addColumn('name', Types::STRING, ['length' => 60]);
        $table->addColumn('value', Types::TEXT, ['notnull' => false]);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $table->setPrimaryKey(['name']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('app_setting');
        $schema->getTable('`user`')->dropColumn('last_login_at');
    }
}
