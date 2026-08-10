<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix wrong casing inserted by Version20260810061845 for exercise_muscle.type.
 *
 * MuscleTypeEnum's backing values are lowercase ('primary'/'secondary'), but that
 * migration wrote the uppercase PHP case names instead, breaking every hydration
 * of ExerciseMuscle (library, routine/workout creation, exercise selector).
 */
final class Version20260810083818 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix uppercase exercise_muscle.type values inserted by Version20260810061845';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE exercise_muscle SET type = 'primary' WHERE type = 'PRIMARY'");
        $this->addSql("UPDATE exercise_muscle SET type = 'secondary' WHERE type = 'SECONDARY'");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
