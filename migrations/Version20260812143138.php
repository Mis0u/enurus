<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Resync public exercises' measurementType against Exercises.json.
 *
 * Farmer walk and Plank were seeded (Version20260810061845) before MeasurementType existed and
 * defaulted to WEIGHT_REPS. Exercises.json now carries the correct value for these two (distance
 * and time) — this migration applies it by name, matching only public exercises (owner_id IS
 * NULL) so it never touches a user's own custom exercise of the same name. Naturally idempotent —
 * re-running it is a harmless no-op resync, same pattern as Version20260810112045.
 */
final class Version20260812143138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Resync public exercises' measurementType against Exercises.json (Farmer walk, Plank)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE exercise SET measurement_type = 'distance' WHERE name = 'farmer_walk.name' AND owner_id IS NULL"
        );
        $this->addSql(
            "UPDATE exercise SET measurement_type = 'time' WHERE name = 'plank.name' AND owner_id IS NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE exercise SET measurement_type = 'weight_reps' WHERE name = 'farmer_walk.name' AND owner_id IS NULL"
        );
        $this->addSql(
            "UPDATE exercise SET measurement_type = 'weight_reps' WHERE name = 'plank.name' AND owner_id IS NULL"
        );
    }
}
