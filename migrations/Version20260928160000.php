<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Anmeldung mit Benutzername: Der Name wird eindeutig.
 * Gibt es schon doppelte Namen (egal welche Schreibweise), bekommen die späteren eine Nummer angehängt.
 */
final class Version20260928160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Benutzername (user.name) eindeutig machen';
    }

    public function preUp(Schema $schema): void
    {
        $seen = [];
        // Tabellenname passend zur Datenbank quoten (MySQL `user`, PostgreSQL/SQLite "user")
        $table = $this->connection->quoteIdentifier('user');
        foreach ($this->connection->fetchAllAssociative("SELECT id, name FROM {$table} ORDER BY id") as $row) {
            $name = trim((string) preg_replace('/\s+/u', ' ', (string) $row['name']));
            $candidate = $name;
            for ($n = 2; isset($seen[mb_strtolower($candidate)]); ++$n) {
                $candidate = mb_substr($name, 0, 26).' '.$n;
            }
            $seen[mb_strtolower($candidate)] = true;
            if ($candidate !== $row['name']) {
                $this->connection->update($table, ['name' => $candidate], ['id' => $row['id']]);
                $this->write(sprintf('Benutzername von #%d angepasst: „%s“ → „%s“', $row['id'], $row['name'], $candidate));
            }
        }
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('`user`')->addUniqueIndex(['name'], 'uniq_user_name');
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('`user`')->dropIndex('uniq_user_name');
    }
}
