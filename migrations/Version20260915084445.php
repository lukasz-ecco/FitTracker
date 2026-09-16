<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260915084445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE training_plan ADD cycle_days INT DEFAULT 7 NOT NULL');
        $this->addSql('ALTER TABLE workout ADD activity_type VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE workout ADD planned_duration_minutes INT DEFAULT NULL');
        $this->addSql('ALTER TABLE workout ADD planned_distance_km DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE training_plan DROP cycle_days');
        $this->addSql('ALTER TABLE workout DROP activity_type');
        $this->addSql('ALTER TABLE workout DROP planned_duration_minutes');
        $this->addSql('ALTER TABLE workout DROP planned_distance_km');
    }
}
