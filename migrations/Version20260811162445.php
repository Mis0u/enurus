<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260811162445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Exercise::measurementType and ExerciseSet::duration (exercices mesurés par durée, ex. gainage)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE exercise ADD measurement_type VARCHAR(20) NOT NULL DEFAULT 'weight_reps'");
        $this->addSql('ALTER TABLE exercise ALTER COLUMN measurement_type DROP DEFAULT');
        $this->addSql('ALTER TABLE exercise_set ADD duration INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercise DROP measurement_type');
        $this->addSql('ALTER TABLE exercise_set DROP duration');
    }
}
