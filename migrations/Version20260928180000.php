<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * App (PWA): Geräte, auf denen Push-Benachrichtigungen aktiviert sind.
 */
final class Version20260928180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tabelle für Push-Abos (ein Eintrag pro Gerät)';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('push_subscription');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('endpoint', Types::TEXT);
        $table->addColumn('endpoint_hash', Types::STRING, ['length' => 64]);
        $table->addColumn('public_key', Types::STRING, ['length' => 255]);
        $table->addColumn('auth_token', Types::STRING, ['length' => 100]);
        $table->addColumn('device_label', Types::STRING, ['length' => 100, 'notnull' => false]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('last_success_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->addColumn('user_id', Types::INTEGER);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['endpoint_hash'], 'uniq_push_endpoint');
        $table->addIndex(['user_id'], 'IDX_562830F3A76ED395');
        $table->addForeignKeyConstraint('`user`', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_562830F3A76ED395');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('push_subscription');
    }
}
