<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210102406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE muscles ADD body_part_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE muscles ADD CONSTRAINT FK_2B4821FBA515F27A FOREIGN KEY (body_part_id) REFERENCES body_parts (id)');
        $this->addSql('CREATE INDEX IDX_2B4821FBA515F27A ON muscles (body_part_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE muscles DROP CONSTRAINT FK_2B4821FBA515F27A');
        $this->addSql('DROP INDEX IDX_2B4821FBA515F27A');
        $this->addSql('ALTER TABLE muscles DROP body_part_id');
    }
}
