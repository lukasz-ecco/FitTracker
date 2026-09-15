<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260821080202 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercises ADD gif_url VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE exercises ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE exercises ADD external_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FA149919F75D7B0 ON exercises (external_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_FA149919F75D7B0');
        $this->addSql('ALTER TABLE exercises DROP gif_url');
        $this->addSql('ALTER TABLE exercises DROP description');
        $this->addSql('ALTER TABLE exercises DROP external_id');
    }
}
