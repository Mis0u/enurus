<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260815143125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bodyweight exercise support: User.bodyweightKg, Exercise.bodyweightPercent, ExerciseSet.bodyweightSnapshotKg';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE exercise ADD bodyweight_percent DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE exercise_set ADD bodyweight_snapshot_kg DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD bodyweight_kg DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE exercise DROP bodyweight_percent');
        $this->addSql('ALTER TABLE exercise_set DROP bodyweight_snapshot_kg');
        $this->addSql('ALTER TABLE users DROP bodyweight_kg');
    }
}
