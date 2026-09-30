<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * „Passwort vergessen“: Tabelle für Reset-Anfragen (nur gehashte Tokens).
 */
final class Version20260928140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tabelle für Passwort-Reset-Anfragen';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('reset_password_request');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('selector', Types::STRING, ['length' => 20]);
        $table->addColumn('hashed_token', Types::STRING, ['length' => 100]);
        $table->addColumn('requested_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('expires_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('user_id', Types::INTEGER);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['user_id'], 'IDX_7CE748AA76ED395');
        $table->addForeignKeyConstraint('`user`', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_7CE748AA76ED395');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('reset_password_request');
    }
}
