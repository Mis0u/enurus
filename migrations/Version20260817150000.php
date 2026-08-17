<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Configure `bodyweightPercent` sur les exercices de référence pour lesquels une charge réelle
 * de poids de corps est documentée (recherche biomécanique — pompes/dips/tractions/relevé de
 * jambes), et retire "superman"/"crunch" du socle : ces deux mouvements ne bougent qu'un segment
 * corporel sans métrique fiable de "% de poids de corps" en littérature, décision produit de les
 * retirer plutôt que de laisser un pourcentage non configuré.
 */
final class Version20260817150000 extends AbstractMigration
{
    /**
     * @var array<string, float>
     */
    private const array BODYWEIGHT_PERCENTAGES = [
        'push_up.name' => 64.0,
        'push_up_diamond.name' => 64.0,
        'dips_chest.name' => 92.0,
        'dips_triceps.name' => 92.0,
        'pull_up.name' => 92.0,
        'chin_up.name' => 92.0,
        'suspended_leg_raise.name' => 41.0,
    ];

    /**
     * @var list<string>
     */
    private const array REMOVED_EXERCISE_NAMES = [
        'superman.name',
        'crunch.name',
    ];

    public function getDescription(): string
    {
        return 'Set bodyweightPercent on reference exercises, remove superman/crunch';
    }

    public function up(Schema $schema): void
    {
        foreach (self::BODYWEIGHT_PERCENTAGES as $name => $percent) {
            $this->addSql('UPDATE exercise SET bodyweight_percent = :percent WHERE name = :name AND owner_id IS NULL', [
                'percent' => $percent,
                'name' => $name,
            ]);
        }

        foreach (self::REMOVED_EXERCISE_NAMES as $name) {
            $this->deleteReferenceExercise($name);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }

    /**
     * Supprime un exercice de référence par son nom, en refusant explicitement de le faire s'il
     * est encore référencé par une séance ou une routine existante — `workout_exercise.exercise_id`
     * et `routine_exercise.exercise_id` n'ont pas de cascade, un DELETE brut échouerait sur une
     * contrainte FK dans ce cas, ou pire, corromprait silencieusement des données utilisateur si la
     * contrainte était retirée. On préfère échouer bruyamment pour forcer une décision manuelle.
     */
    private function deleteReferenceExercise(string $name): void
    {
        $exerciseId = $this->connection->fetchOne(
            'SELECT id FROM exercise WHERE name = :name AND owner_id IS NULL',
            [
                'name' => $name,
            ],
        );

        if (! is_string($exerciseId)) {
            return;
        }

        $usageCountRaw = $this->connection->fetchOne(
            'SELECT
                (SELECT COUNT(*) FROM workout_exercise WHERE exercise_id = :id)
                + (SELECT COUNT(*) FROM routine_exercise WHERE exercise_id = :id)',
            [
                'id' => $exerciseId,
            ],
        );

        if (! is_numeric($usageCountRaw)) {
            throw new \LogicException('Unexpected non-numeric usage count while checking reference exercise deletion safety.');
        }

        $usageCount = (int) $usageCountRaw;

        if (0 < $usageCount) {
            throw new \LogicException(\sprintf(
                'Impossible de supprimer l\'exercice de référence "%s" : encore référencé par %d séance(s)/routine(s).',
                $name,
                $usageCount,
            ));
        }

        $this->addSql('DELETE FROM exercise_muscle WHERE exercise_id = :id', [
            'id' => $exerciseId,
        ]);
        $this->addSql('DELETE FROM exercise WHERE id = :id', [
            'id' => $exerciseId,
        ]);
    }
}
