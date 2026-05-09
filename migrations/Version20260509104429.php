<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260509104429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD gender INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD height INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD weight INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD profile_picture VARCHAR(150) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP gender');
        $this->addSql('ALTER TABLE "user" DROP height');
        $this->addSql('ALTER TABLE "user" DROP weight');
        $this->addSql('ALTER TABLE "user" DROP profile_picture');
    }
}
