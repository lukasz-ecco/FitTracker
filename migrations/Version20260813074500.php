<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migruje typy celów treningowych z PHP Enum (string) do encji bazodanowej (GoalType).
 *
 * 1. Tworzy tabelę `goal_type` i wypełnia ją 7 domyślnymi wartościami.
 * 2. Przebudowuje kolumnę `goal_type` w `training_goal` na klucz obcy.
 * 3. Przebudowuje kolumnę `goal_type` w `exercise_supported_goal` na klucz obcy.
 */
final class Version20260813074500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migracja typów celów treningowych z Enum do tabeli goal_type';
    }

    public function up(Schema $schema): void
    {
        // 1. Tworzymy nową tabelę słownikową
        $this->addSql(<<<'SQL'
            CREATE TABLE goal_type (
                id SERIAL NOT NULL,
                name VARCHAR(50) NOT NULL,
                label VARCHAR(100) NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_GOAL_TYPE_NAME ON goal_type (name)');

        // 2. Wypełniamy domyślnymi wartościami (odwzorowanie poprzedniego Enum)
        $this->addSql(<<<'SQL'
            INSERT INTO goal_type (name, label) VALUES
                ('weight_loss',     'Redukcja wagi'),
                ('muscle_gain',     'Budowa masy mięśniowej'),
                ('strength',        'Wzrost siły'),
                ('endurance',       'Wytrzymałość'),
                ('flexibility',     'Elastyczność i stretching'),
                ('rehabilitation',  'Rehabilitacja'),
                ('general_fitness', 'Ogólna sprawność')
        SQL);

        // 3. Przebudowujemy tabelę training_goal
        //    Dodajemy nową kolumnę FK, wypełniamy ją na podstawie starego stringa, usuwamy stary string.
        $this->addSql('ALTER TABLE training_goal ADD COLUMN goal_type_id INT');
        $this->addSql(<<<'SQL'
            UPDATE training_goal tg
            SET goal_type_id = (
                SELECT id FROM goal_type WHERE name = tg.goal_type
            )
        SQL);
        $this->addSql('ALTER TABLE training_goal DROP COLUMN goal_type');
        $this->addSql('ALTER TABLE training_goal ALTER COLUMN goal_type_id SET NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE training_goal
                ADD CONSTRAINT FK_TRAINING_GOAL_GOAL_TYPE
                FOREIGN KEY (goal_type_id) REFERENCES goal_type(id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql('CREATE INDEX IDX_TRAINING_GOAL_TYPE ON training_goal (goal_type_id)');

        // 4. Przebudowujemy tabelę exercise_supported_goal
        $this->addSql('ALTER TABLE exercise_supported_goal ADD COLUMN goal_type_id INT');
        $this->addSql(<<<'SQL'
            UPDATE exercise_supported_goal esg
            SET goal_type_id = (
                SELECT id FROM goal_type WHERE name = esg.goal_type
            )
        SQL);
        $this->addSql('DROP INDEX unique_exercise_goal');
        $this->addSql('ALTER TABLE exercise_supported_goal DROP COLUMN goal_type');
        $this->addSql('ALTER TABLE exercise_supported_goal ALTER COLUMN goal_type_id SET NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE exercise_supported_goal
                ADD CONSTRAINT FK_EXERCISE_SUPPORTED_GOAL_GOAL_TYPE
                FOREIGN KEY (goal_type_id) REFERENCES goal_type(id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql('CREATE UNIQUE INDEX unique_exercise_goal ON exercise_supported_goal (exercise_id, goal_type_id)');
    }

    public function down(Schema $schema): void
    {
        // Przywracamy exercise_supported_goal
        $this->addSql('ALTER TABLE exercise_supported_goal ADD COLUMN goal_type VARCHAR(50)');
        $this->addSql(<<<'SQL'
            UPDATE exercise_supported_goal esg
            SET goal_type = (
                SELECT name FROM goal_type WHERE id = esg.goal_type_id
            )
        SQL);
        $this->addSql('DROP INDEX unique_exercise_goal');
        $this->addSql('ALTER TABLE exercise_supported_goal DROP CONSTRAINT FK_EXERCISE_SUPPORTED_GOAL_GOAL_TYPE');
        $this->addSql('ALTER TABLE exercise_supported_goal DROP COLUMN goal_type_id');
        $this->addSql('ALTER TABLE exercise_supported_goal ALTER COLUMN goal_type SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX unique_exercise_goal ON exercise_supported_goal (exercise_id, goal_type)');

        // Przywracamy training_goal
        $this->addSql('ALTER TABLE training_goal ADD COLUMN goal_type VARCHAR(50)');
        $this->addSql(<<<'SQL'
            UPDATE training_goal tg
            SET goal_type = (
                SELECT name FROM goal_type WHERE id = tg.goal_type_id
            )
        SQL);
        $this->addSql('ALTER TABLE training_goal DROP CONSTRAINT FK_TRAINING_GOAL_GOAL_TYPE');
        $this->addSql('ALTER TABLE training_goal DROP COLUMN goal_type_id');
        $this->addSql('ALTER TABLE training_goal ALTER COLUMN goal_type SET NOT NULL');

        // Usuwamy nową tabelę
        $this->addSql('DROP INDEX UNIQ_GOAL_TYPE_NAME');
        $this->addSql('DROP TABLE goal_type');
    }
}
