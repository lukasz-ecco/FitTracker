<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Clean up body_parts duplicates, fix Polish anatomical names, and add sort_order & is_measurable.
 */
final class Version20260916120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clean up body parts duplicates, fix translations (Wstecz -> Plecy, Ud -> Uda, etc.), and add anatomical ordering';
    }

    public function up(Schema $schema): void
    {
        // 1. Dodanie kolumn sort_order i is_measurable
        $this->addSql('ALTER TABLE body_parts ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 100 NOT NULL');
        $this->addSql('ALTER TABLE body_parts ADD COLUMN IF NOT EXISTS is_measurable BOOLEAN DEFAULT true NOT NULL');

        // 2. Przepięcie duplikatów w tabeli muscles i meseurments
        // ID 5 (Klatka Piersiowa) -> ID 1 (Klatka Piersiowa)
        $this->addSql('UPDATE muscles SET body_part_id = 1 WHERE body_part_id = 5');
        $this->addSql('UPDATE meseurments SET body_part_id = 1 WHERE body_part_id = 5');
        $this->addSql('DELETE FROM body_parts WHERE id = 5');

        // ID 6 (Ud) -> ID 2 (Uda)
        $this->addSql('UPDATE muscles SET body_part_id = 2 WHERE body_part_id = 6');
        $this->addSql('UPDATE meseurments SET body_part_id = 2 WHERE body_part_id = 6');
        $this->addSql('DELETE FROM body_parts WHERE id = 6');

        // ID 8 (Ramiona) -> ID 7 (Ramiona)
        $this->addSql('UPDATE muscles SET body_part_id = 7 WHERE body_part_id = 8');
        $this->addSql('UPDATE meseurments SET body_part_id = 7 WHERE body_part_id = 8');
        $this->addSql('DELETE FROM body_parts WHERE id = 8');

        // 3. Poprawa nazw
        $this->addSql("UPDATE body_parts SET name = 'Plecy' WHERE id = 4 OR LOWER(name) = 'wstecz'");
        $this->addSql("UPDATE body_parts SET name = 'Uda' WHERE id = 2 OR LOWER(name) IN ('udo', 'ud')");
        $this->addSql("UPDATE body_parts SET name = 'Łydki' WHERE id = 10 OR LOWER(name) = 'podudzia'");

        // 4. Dodanie Bioder jeśli nie istnieją
        $this->addSql("INSERT INTO body_parts (name, sort_order, is_measurable) 
            SELECT 'Biodra', 70, true 
            WHERE NOT EXISTS (SELECT 1 FROM body_parts WHERE LOWER(name) = 'biodra')");

        // 5. Ustawienie sort_order i is_measurable według kolejności anatomicznej
        $this->addSql("UPDATE body_parts SET sort_order = 10, is_measurable = true WHERE LOWER(name) = 'szyja'");
        $this->addSql("UPDATE body_parts SET sort_order = 20, is_measurable = true WHERE LOWER(name) = 'klatka piersiowa'");
        $this->addSql("UPDATE body_parts SET sort_order = 30, is_measurable = false WHERE LOWER(name) = 'plecy'");
        $this->addSql("UPDATE body_parts SET sort_order = 40, is_measurable = true WHERE LOWER(name) = 'ramiona'");
        $this->addSql("UPDATE body_parts SET sort_order = 50, is_measurable = true WHERE LOWER(name) = 'przedramiona'");
        $this->addSql("UPDATE body_parts SET sort_order = 60, is_measurable = true WHERE LOWER(name) = 'talia'");
        $this->addSql("UPDATE body_parts SET sort_order = 70, is_measurable = true WHERE LOWER(name) = 'biodra'");
        $this->addSql("UPDATE body_parts SET sort_order = 80, is_measurable = true WHERE LOWER(name) IN ('uda', 'udo')");
        $this->addSql("UPDATE body_parts SET sort_order = 90, is_measurable = true WHERE LOWER(name) IN ('łydki', 'podudzia')");
        $this->addSql("UPDATE body_parts SET sort_order = 999, is_measurable = false WHERE LOWER(name) LIKE '%cardio%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE body_parts DROP COLUMN IF EXISTS sort_order');
        $this->addSql('ALTER TABLE body_parts DROP COLUMN IF EXISTS is_measurable');
    }
}
