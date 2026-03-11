<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260309065352 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE exercise (id UUID NOT NULL, name VARCHAR(150) NOT NULL, description TEXT DEFAULT NULL, is_public BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, owner_id UUID DEFAULT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_AEDAD51C7E3C61F9 ON exercise (owner_id)');
        $this->addSql('CREATE INDEX IDX_AEDAD51CB03A8386 ON exercise (created_by_id)');
        $this->addSql('CREATE INDEX IDX_AEDAD51C896DBBDE ON exercise (updated_by_id)');
        $this->addSql('CREATE TABLE exercise_muscle (id UUID NOT NULL, type VARCHAR(20) NOT NULL, exercise_id UUID NOT NULL, muscle_group_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_865E8C84E934951A ON exercise_muscle (exercise_id)');
        $this->addSql('CREATE INDEX IDX_865E8C8444004D0 ON exercise_muscle (muscle_group_id)');
        $this->addSql('CREATE TABLE exercise_set (id UUID NOT NULL, position INT NOT NULL, weight DOUBLE PRECISION NOT NULL, reps INT NOT NULL, workout_exercise_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_704B80A0E435DB6B ON exercise_set (workout_exercise_id)');
        $this->addSql('CREATE TABLE muscle_group (id UUID NOT NULL, name VARCHAR(100) NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_323D098EB03A8386 ON muscle_group (created_by_id)');
        $this->addSql('CREATE INDEX IDX_323D098E896DBBDE ON muscle_group (updated_by_id)');
        $this->addSql('CREATE TABLE routine (id UUID NOT NULL, name VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, owner_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4BF6D8D67E3C61F9 ON routine (owner_id)');
        $this->addSql('CREATE INDEX IDX_4BF6D8D6B03A8386 ON routine (created_by_id)');
        $this->addSql('CREATE INDEX IDX_4BF6D8D6896DBBDE ON routine (updated_by_id)');
        $this->addSql('CREATE TABLE routine_exercise (id UUID NOT NULL, position INT NOT NULL, routine_id UUID NOT NULL, exercise_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_50CE302AF27A94C7 ON routine_exercise (routine_id)');
        $this->addSql('CREATE INDEX IDX_50CE302AE934951A ON routine_exercise (exercise_id)');
        $this->addSql('CREATE TABLE workout (id UUID NOT NULL, performed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, duration INT DEFAULT NULL, owner_id UUID NOT NULL, routine_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_649FFB727E3C61F9 ON workout (owner_id)');
        $this->addSql('CREATE INDEX IDX_649FFB72F27A94C7 ON workout (routine_id)');
        $this->addSql('CREATE TABLE workout_exercise (id UUID NOT NULL, position INT NOT NULL, workout_id UUID NOT NULL, exercise_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_76AB38AAA6CCCFC9 ON workout_exercise (workout_id)');
        $this->addSql('CREATE INDEX IDX_76AB38AAE934951A ON workout_exercise (exercise_id)');
        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51CB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51C896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exercise_muscle ADD CONSTRAINT FK_865E8C84E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exercise_muscle ADD CONSTRAINT FK_865E8C8444004D0 FOREIGN KEY (muscle_group_id) REFERENCES muscle_group (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exercise_set ADD CONSTRAINT FK_704B80A0E435DB6B FOREIGN KEY (workout_exercise_id) REFERENCES workout_exercise (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE muscle_group ADD CONSTRAINT FK_323D098EB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE muscle_group ADD CONSTRAINT FK_323D098E896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE routine ADD CONSTRAINT FK_4BF6D8D67E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE routine ADD CONSTRAINT FK_4BF6D8D6B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE routine ADD CONSTRAINT FK_4BF6D8D6896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE routine_exercise ADD CONSTRAINT FK_50CE302AF27A94C7 FOREIGN KEY (routine_id) REFERENCES routine (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE routine_exercise ADD CONSTRAINT FK_50CE302AE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE workout ADD CONSTRAINT FK_649FFB727E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE workout ADD CONSTRAINT FK_649FFB72F27A94C7 FOREIGN KEY (routine_id) REFERENCES routine (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE workout_exercise ADD CONSTRAINT FK_76AB38AAA6CCCFC9 FOREIGN KEY (workout_id) REFERENCES workout (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE workout_exercise ADD CONSTRAINT FK_76AB38AAE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercise DROP CONSTRAINT FK_AEDAD51C7E3C61F9');
        $this->addSql('ALTER TABLE exercise DROP CONSTRAINT FK_AEDAD51CB03A8386');
        $this->addSql('ALTER TABLE exercise DROP CONSTRAINT FK_AEDAD51C896DBBDE');
        $this->addSql('ALTER TABLE exercise_muscle DROP CONSTRAINT FK_865E8C84E934951A');
        $this->addSql('ALTER TABLE exercise_muscle DROP CONSTRAINT FK_865E8C8444004D0');
        $this->addSql('ALTER TABLE exercise_set DROP CONSTRAINT FK_704B80A0E435DB6B');
        $this->addSql('ALTER TABLE muscle_group DROP CONSTRAINT FK_323D098EB03A8386');
        $this->addSql('ALTER TABLE muscle_group DROP CONSTRAINT FK_323D098E896DBBDE');
        $this->addSql('ALTER TABLE routine DROP CONSTRAINT FK_4BF6D8D67E3C61F9');
        $this->addSql('ALTER TABLE routine DROP CONSTRAINT FK_4BF6D8D6B03A8386');
        $this->addSql('ALTER TABLE routine DROP CONSTRAINT FK_4BF6D8D6896DBBDE');
        $this->addSql('ALTER TABLE routine_exercise DROP CONSTRAINT FK_50CE302AF27A94C7');
        $this->addSql('ALTER TABLE routine_exercise DROP CONSTRAINT FK_50CE302AE934951A');
        $this->addSql('ALTER TABLE workout DROP CONSTRAINT FK_649FFB727E3C61F9');
        $this->addSql('ALTER TABLE workout DROP CONSTRAINT FK_649FFB72F27A94C7');
        $this->addSql('ALTER TABLE workout_exercise DROP CONSTRAINT FK_76AB38AAA6CCCFC9');
        $this->addSql('ALTER TABLE workout_exercise DROP CONSTRAINT FK_76AB38AAE934951A');
        $this->addSql('DROP TABLE exercise');
        $this->addSql('DROP TABLE exercise_muscle');
        $this->addSql('DROP TABLE exercise_set');
        $this->addSql('DROP TABLE muscle_group');
        $this->addSql('DROP TABLE routine');
        $this->addSql('DROP TABLE routine_exercise');
        $this->addSql('DROP TABLE workout');
        $this->addSql('DROP TABLE workout_exercise');
    }
}
