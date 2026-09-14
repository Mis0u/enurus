<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260914073549 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add exercise_goal table.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE exercise_goal (id UUID NOT NULL, measurement_type VARCHAR(20) NOT NULL, target_weight DOUBLE PRECISION DEFAULT NULL, target_duration INT DEFAULT NULL, target_distance INT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, owner_id UUID NOT NULL, exercise_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A5F9899C7E3C61F9 ON exercise_goal (owner_id)');
        $this->addSql('CREATE INDEX IDX_A5F9899CE934951A ON exercise_goal (exercise_id)');
        $this->addSql('CREATE INDEX IDX_A5F9899CB03A8386 ON exercise_goal (created_by_id)');
        $this->addSql('CREATE INDEX IDX_A5F9899C896DBBDE ON exercise_goal (updated_by_id)');
        $this->addSql('CREATE UNIQUE INDEX exercise_goal_owner_exercise_unique ON exercise_goal (owner_id, exercise_id)');
        $this->addSql('ALTER TABLE exercise_goal ADD CONSTRAINT FK_A5F9899C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exercise_goal ADD CONSTRAINT FK_A5F9899CE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exercise_goal ADD CONSTRAINT FK_A5F9899CB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exercise_goal ADD CONSTRAINT FK_A5F9899C896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercise_goal DROP CONSTRAINT FK_A5F9899C7E3C61F9');
        $this->addSql('ALTER TABLE exercise_goal DROP CONSTRAINT FK_A5F9899CE934951A');
        $this->addSql('ALTER TABLE exercise_goal DROP CONSTRAINT FK_A5F9899CB03A8386');
        $this->addSql('ALTER TABLE exercise_goal DROP CONSTRAINT FK_A5F9899C896DBBDE');
        $this->addSql('DROP TABLE exercise_goal');
    }
}
