<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dodaje kolumnę supported_goals do tabeli exercises.
 */
final class Version20260812130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dodaje kolumnę supported_goals do tabeli exercises';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE exercises ADD supported_goals TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE exercises DROP COLUMN supported_goals');
    }
}
