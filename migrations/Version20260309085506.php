<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260309085506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercise_muscle DROP CONSTRAINT fk_865e8c84e934951a');
        $this->addSql('ALTER TABLE exercise_muscle ADD CONSTRAINT FK_865E8C84E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE exercise_set DROP CONSTRAINT fk_704b80a0e435db6b');
        $this->addSql('ALTER TABLE exercise_set ADD CONSTRAINT FK_704B80A0E435DB6B FOREIGN KEY (workout_exercise_id) REFERENCES workout_exercise (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE reset_password_request ALTER requested_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE reset_password_request ALTER expires_at TYPE TIMESTAMP(0) WITH TIME ZONE');
        $this->addSql('ALTER TABLE routine_exercise DROP CONSTRAINT fk_50ce302af27a94c7');
        $this->addSql('ALTER TABLE routine_exercise ADD CONSTRAINT FK_50CE302AF27A94C7 FOREIGN KEY (routine_id) REFERENCES routine (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercise_muscle DROP CONSTRAINT FK_865E8C84E934951A');
        $this->addSql('ALTER TABLE exercise_muscle ADD CONSTRAINT fk_865e8c84e934951a FOREIGN KEY (exercise_id) REFERENCES exercise (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE exercise_set DROP CONSTRAINT FK_704B80A0E435DB6B');
        $this->addSql('ALTER TABLE exercise_set ADD CONSTRAINT fk_704b80a0e435db6b FOREIGN KEY (workout_exercise_id) REFERENCES workout_exercise (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reset_password_request ALTER requested_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE reset_password_request ALTER expires_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE routine_exercise DROP CONSTRAINT FK_50CE302AF27A94C7');
        $this->addSql('ALTER TABLE routine_exercise ADD CONSTRAINT fk_50ce302af27a94c7 FOREIGN KEY (routine_id) REFERENCES routine (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
