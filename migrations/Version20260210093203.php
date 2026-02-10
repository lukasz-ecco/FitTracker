<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210093203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ALTER name DROP NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER surrname DROP NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER age DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ALTER name SET NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER surrname SET NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER age SET NOT NULL');
    }
}
