<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Zmienia kolumnę supported_goals z TEXT (simple_array) na JSON.
 */
final class Version20260812183700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Zmienia kolumnę supported_goals z TEXT (simple_array) na JSON';
    }

    public function up(Schema $schema): void
    {
        // 1. Dodaj tymczasową kolumnę JSON
        $this->addSql('ALTER TABLE exercises ADD COLUMN supported_goals_json JSON DEFAULT NULL');

        // 2. Przekonwertuj dane CSV → JSON (bez subquery w USING)
        $this->addSql(<<<'SQL'
            UPDATE exercises
            SET supported_goals_json = CASE
                WHEN supported_goals IS NULL THEN NULL
                WHEN supported_goals = '' THEN '[]'::json
                ELSE ('["' || replace(supported_goals, ',', '","') || '"]')::json
            END
        SQL);

        // 3. Usuń starą kolumnę i zmień nazwę nowej
        $this->addSql('ALTER TABLE exercises DROP COLUMN supported_goals');
        $this->addSql('ALTER TABLE exercises RENAME COLUMN supported_goals_json TO supported_goals');
    }

    public function down(Schema $schema): void
    {
        // 1. Dodaj tymczasową kolumnę TEXT
        $this->addSql('ALTER TABLE exercises ADD COLUMN supported_goals_text TEXT DEFAULT NULL');

        // 2. Przekonwertuj JSON → CSV
        $this->addSql(<<<'SQL'
            UPDATE exercises
            SET supported_goals_text = CASE
                WHEN supported_goals IS NULL THEN NULL
                ELSE array_to_string(ARRAY(SELECT json_array_elements_text(supported_goals)), ',')
            END
        SQL);

        // 3. Usuń starą kolumnę i zmień nazwę nowej
        $this->addSql('ALTER TABLE exercises DROP COLUMN supported_goals');
        $this->addSql('ALTER TABLE exercises RENAME COLUMN supported_goals_text TO supported_goals');
    }
}
