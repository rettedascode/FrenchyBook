<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Grundschema von FrenchyBook.
 *
 * Bewusst über die Schema-API statt mit rohem SQL geschrieben: Doctrine erzeugt daraus
 * das passende SQL für SQLite, MySQL/MariaDB und PostgreSQL. Ein Datenbankwechsel
 * braucht also nur eine andere DATABASE_URL.
 */
final class Version20260928000722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grundschema: Nutzer, Bücher, Autoren, Genres, Ausleihen und Anfragen';
    }

    public function up(Schema $schema): void
    {
        $user = $schema->createTable('`user`');
        $user->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $user->addColumn('email', Types::STRING, ['length' => 180]);
        $user->addColumn('name', Types::STRING, ['length' => 60]);
        $user->addColumn('roles', Types::JSON);
        $user->addColumn('password', Types::STRING, ['length' => 255]);
        $user->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $user->setPrimaryKey(['id']);
        $user->addUniqueIndex(['email'], 'uniq_user_email');

        $author = $schema->createTable('author');
        $author->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $author->addColumn('name', Types::STRING, ['length' => 150]);
        $author->setPrimaryKey(['id']);
        $author->addUniqueIndex(['name'], 'uniq_author_name');

        $genre = $schema->createTable('genre');
        $genre->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $genre->addColumn('name', Types::STRING, ['length' => 80]);
        $genre->setPrimaryKey(['id']);
        $genre->addUniqueIndex(['name'], 'uniq_genre_name');

        $book = $schema->createTable('book');
        $book->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $book->addColumn('title', Types::STRING, ['length' => 255]);
        $book->addColumn('subtitle', Types::STRING, ['length' => 255, 'notnull' => false]);
        $book->addColumn('cover_image', Types::STRING, ['length' => 255, 'notnull' => false]);
        $book->addColumn('isbn', Types::STRING, ['length' => 13, 'notnull' => false]);
        $book->addColumn('publisher', Types::STRING, ['length' => 150, 'notnull' => false]);
        $book->addColumn('published_year', Types::SMALLINT, ['notnull' => false]);
        $book->addColumn('language', Types::STRING, ['length' => 40, 'notnull' => false]);
        $book->addColumn('page_count', Types::INTEGER, ['notnull' => false]);
        $book->addColumn('format', Types::STRING, ['length' => 20, 'notnull' => false]);
        $book->addColumn('series', Types::STRING, ['length' => 150, 'notnull' => false]);
        $book->addColumn('series_number', Types::SMALLINT, ['notnull' => false]);
        $book->addColumn('description', Types::TEXT, ['notnull' => false]);
        $book->addColumn('book_condition', Types::STRING, ['length' => 20, 'notnull' => false]);
        $book->addColumn('owner_note', Types::STRING, ['length' => 500, 'notnull' => false]);
        $book->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $book->addColumn('updated_at', Types::DATETIME_IMMUTABLE);
        $book->addColumn('owner_id', Types::INTEGER);
        $book->setPrimaryKey(['id']);
        $book->addIndex(['title'], 'idx_book_title');
        $book->addIndex(['isbn'], 'idx_book_isbn');
        $book->addIndex(['created_at'], 'idx_book_created');
        $book->addIndex(['owner_id'], 'IDX_CBE5A3317E3C61F9');
        $book->addForeignKeyConstraint('`user`', ['owner_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_CBE5A3317E3C61F9');

        $bookAuthor = $schema->createTable('book_author');
        $bookAuthor->addColumn('book_id', Types::INTEGER);
        $bookAuthor->addColumn('author_id', Types::INTEGER);
        $bookAuthor->setPrimaryKey(['book_id', 'author_id']);
        $bookAuthor->addIndex(['book_id'], 'IDX_9478D34516A2B381');
        $bookAuthor->addIndex(['author_id'], 'IDX_9478D345F675F31B');
        $bookAuthor->addForeignKeyConstraint('book', ['book_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_9478D34516A2B381');
        $bookAuthor->addForeignKeyConstraint('author', ['author_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_9478D345F675F31B');

        $bookGenre = $schema->createTable('book_genre');
        $bookGenre->addColumn('book_id', Types::INTEGER);
        $bookGenre->addColumn('genre_id', Types::INTEGER);
        $bookGenre->setPrimaryKey(['book_id', 'genre_id']);
        $bookGenre->addIndex(['book_id'], 'IDX_8D92268116A2B381');
        $bookGenre->addIndex(['genre_id'], 'IDX_8D9226814296D31F');
        $bookGenre->addForeignKeyConstraint('book', ['book_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_8D92268116A2B381');
        $bookGenre->addForeignKeyConstraint('genre', ['genre_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_8D9226814296D31F');

        $loan = $schema->createTable('loan');
        $loan->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $loan->addColumn('lent_at', Types::DATETIME_IMMUTABLE);
        $loan->addColumn('due_at', Types::DATE_IMMUTABLE, ['notnull' => false]);
        $loan->addColumn('returned_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $loan->addColumn('note', Types::STRING, ['length' => 255, 'notnull' => false]);
        $loan->addColumn('reminder_sent_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $loan->addColumn('book_id', Types::INTEGER);
        $loan->addColumn('borrower_id', Types::INTEGER);
        $loan->setPrimaryKey(['id']);
        $loan->addIndex(['book_id', 'returned_at'], 'idx_loan_active');
        $loan->addIndex(['returned_at', 'due_at'], 'idx_loan_due');
        $loan->addIndex(['book_id'], 'IDX_C5D30D0316A2B381');
        $loan->addIndex(['borrower_id'], 'IDX_C5D30D0311CE312B');
        $loan->addForeignKeyConstraint('book', ['book_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_C5D30D0316A2B381');
        $loan->addForeignKeyConstraint('`user`', ['borrower_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_C5D30D0311CE312B');

        $request = $schema->createTable('loan_request');
        $request->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $request->addColumn('status', Types::STRING, ['length' => 20]);
        $request->addColumn('message', Types::STRING, ['length' => 300, 'notnull' => false]);
        $request->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $request->addColumn('responded_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $request->addColumn('book_id', Types::INTEGER);
        $request->addColumn('requester_id', Types::INTEGER);
        $request->setPrimaryKey(['id']);
        $request->addIndex(['status'], 'idx_request_status');
        $request->addIndex(['book_id'], 'IDX_15D801EB16A2B381');
        $request->addIndex(['requester_id'], 'IDX_15D801EBED442CF4');
        $request->addForeignKeyConstraint('book', ['book_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_15D801EB16A2B381');
        $request->addForeignKeyConstraint('`user`', ['requester_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_15D801EBED442CF4');
    }

    public function down(Schema $schema): void
    {
        foreach (['loan_request', 'loan', 'book_genre', 'book_author', 'book', 'genre', 'author', '`user`'] as $table) {
            $schema->dropTable($table);
        }
    }
}
