<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Anforderungen „La Bibliothèque Frenchie de Cologne“:
 * Vor-/Nachname und Kölner Bezirk, Auszeichnungen/Kommentar/Bewertung am Buch,
 * Verlängerung und Vorab-Erinnerung bei Ausleihen, Warteliste.
 */
final class Version20260930100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bezirk & Namen, Buch-Bewertung, Verlängerung, Warteliste';
    }

    public function up(Schema $schema): void
    {
        $user = $schema->getTable('`user`');
        $user->addColumn('first_name', Types::STRING, ['length' => 50, 'notnull' => false]);
        $user->addColumn('last_name', Types::STRING, ['length' => 50, 'notnull' => false]);
        $user->addColumn('district', Types::STRING, ['length' => 20, 'notnull' => false]);

        $book = $schema->getTable('book');
        $book->addColumn('awards', Types::STRING, ['length' => 255, 'notnull' => false]);
        $book->addColumn('review', Types::TEXT, ['notnull' => false]);
        $book->addColumn('rating', Types::SMALLINT, ['notnull' => false]);

        $loan = $schema->getTable('loan');
        $loan->addColumn('extension_count', Types::SMALLINT, ['default' => 0]);
        $loan->addColumn('due_soon_reminder_sent_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);

        $waitlist = $schema->createTable('waitlist_entry');
        $waitlist->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $waitlist->addColumn('book_id', Types::INTEGER);
        $waitlist->addColumn('user_id', Types::INTEGER);
        $waitlist->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $waitlist->addColumn('notified_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $waitlist->setPrimaryKey(['id']);
        $waitlist->addUniqueIndex(['book_id', 'user_id'], 'uniq_waitlist_book_user');
        $waitlist->addIndex(['book_id'], 'IDX_6744757416A2B381');
        $waitlist->addIndex(['user_id'], 'IDX_67447574A76ED395');
        $waitlist->addForeignKeyConstraint('book', ['book_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_6744757416A2B381');
        $waitlist->addForeignKeyConstraint('`user`', ['user_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_67447574A76ED395');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('waitlist_entry');
        $loan = $schema->getTable('loan');
        $loan->dropColumn('extension_count');
        $loan->dropColumn('due_soon_reminder_sent_at');
        $book = $schema->getTable('book');
        $book->dropColumn('awards');
        $book->dropColumn('review');
        $book->dropColumn('rating');
        $user = $schema->getTable('`user`');
        $user->dropColumn('first_name');
        $user->dropColumn('last_name');
        $user->dropColumn('district');
    }
}
