<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mehrsprachigkeit (Deutsch/Französisch):
 * - Jeder Nutzer bekommt eine Sprache für Oberfläche und E-Mails.
 * - Buchsprachen werden als ISO-Code statt als deutscher Name gespeichert.
 */
final class Version20260928120000 extends AbstractMigration
{
    private const NAME_TO_CODE = [
        'Deutsch' => 'de', 'Englisch' => 'en', 'Französisch' => 'fr', 'Spanisch' => 'es',
        'Italienisch' => 'it', 'Niederländisch' => 'nl', 'Portugiesisch' => 'pt', 'Schwedisch' => 'sv',
        'Dänisch' => 'da', 'Norwegisch' => 'no', 'Polnisch' => 'pl', 'Russisch' => 'ru',
        'Türkisch' => 'tr', 'Griechisch' => 'el', 'Japanisch' => 'ja', 'Latein' => 'la',
    ];

    public function getDescription(): string
    {
        return 'Sprache pro Nutzer; Buchsprachen als ISO-Code';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('`user`')->addColumn('locale', Types::STRING, ['length' => 5, 'default' => 'de']);

        foreach (self::NAME_TO_CODE as $name => $code) {
            $this->addSql('UPDATE book SET language = ? WHERE language = ?', [$code, $name]);
        }
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('`user`')->dropColumn('locale');

        foreach (self::NAME_TO_CODE as $name => $code) {
            $this->addSql('UPDATE book SET language = ? WHERE language = ?', [$name, $code]);
        }
    }
}
