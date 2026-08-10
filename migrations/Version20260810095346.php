<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\DataFixtures\Service\Type\TypeService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

/**
 * Split the generic "name.traps" MuscleGroup into "name.traps_upper" and "name.traps_lower".
 *
 * The trapezius has no anterior/posterior split (it's a purely posterior muscle), but upper vs
 * lower is a meaningful and commonly used distinction for programming (shrugs target upper
 * traps, rows/face pulls target lower traps) — "name.traps_middle" already existed as its own
 * group, this fills the gap. Reads Muscle-groups.json (single source of truth, shared with dev
 * fixtures) for the new groups' position/svgIds, and reassigns each ExerciseMuscle row that
 * pointed at the old "name.traps" group based on which exercise it belongs to.
 */
final class Version20260810095346 extends AbstractMigration
{
    /**
     * @var array<string, string> exercise name => target group name ('name.traps_upper'|'name.traps_lower')
     */
    private const array EXERCISE_TARGET_GROUP = [
        'barbell_shrug.name' => 'name.traps_upper',
        'dumbbell_shrug.name' => 'name.traps_upper',
        'lateral_raise.name' => 'name.traps_upper',
        'farmer_walk.name' => 'name.traps_upper',
        'deadlift.name' => 'name.traps_upper',
        'barbell_row.name' => 'name.traps_lower',
        'face_pull.name' => 'name.traps_lower',
        'rear_delt_fly.name' => 'name.traps_lower',
        // Dev/test-only fixture exercise (never present in prod, cf. ExerciseFixtures::loadReverseFlyExercise) —
        // included so this migration also runs cleanly on a dev database that hasn't reloaded fixtures yet.
        'Reverse fly' => 'name.traps_lower',
    ];

    public function getDescription(): string
    {
        return 'Split MuscleGroup "name.traps" into "name.traps_upper" and "name.traps_lower"';
    }

    public function up(Schema $schema): void
    {
        $oldTrapsId = $this->connection->fetchOne("SELECT id FROM muscle_group WHERE name = 'name.traps'");

        $this->skipIf(false === $oldTrapsId, '"name.traps" no longer exists, already split');

        if (! is_string($oldTrapsId)) {
            throw new \LogicException('Unexpected non-string id for "name.traps" muscle group.');
        }

        // Shift every group after "name.traps_middle" by one position to make room for the new
        // "name.traps_lower" group, matching Muscle-groups.json's new ordering.
        $this->addSql('UPDATE muscle_group SET position = position + 1 WHERE position >= 5');

        $newGroupIds = $this->insertNewGroups();

        foreach (self::EXERCISE_TARGET_GROUP as $exerciseName => $targetGroupName) {
            $this->addSql(
                'UPDATE exercise_muscle SET muscle_group_id = :newGroupId '
                . 'WHERE muscle_group_id = :oldGroupId '
                . 'AND exercise_id = (SELECT id FROM exercise WHERE name = :exerciseName)',
                [
                    'newGroupId' => $newGroupIds[$targetGroupName],
                    'oldGroupId' => $oldTrapsId,
                    'exerciseName' => $exerciseName,
                ]
            );
        }

        $this->addSql('DELETE FROM muscle_group WHERE id = :id', [
            'id' => $oldTrapsId,
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }

    /**
     * @return array<string, string> group name ('name.traps_upper'|'name.traps_lower') => uuid généré
     */
    private function insertNewGroups(): array
    {
        $typeService = new TypeService();
        $path = \sprintf('%s/src/DataFixtures/JSON/Muscle-groups.json', \dirname(__DIR__));
        $content = file_get_contents($path);

        if (false === $content) {
            throw new \RuntimeException(\sprintf('Impossible de lire le fichier : %s', $path));
        }

        $muscleGroups = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($muscleGroups)) {
            throw new \RuntimeException(\sprintf('Le fichier JSON ne contient pas un tableau : %s', $path));
        }

        $ids = [];

        foreach ($muscleGroups as $data) {
            if (! is_array($data)) {
                throw new \RuntimeException(\sprintf('Une entrée de %s n\'est pas un tableau.', $path));
            }

            /** @var array<string, mixed> $data */
            $name = $typeService->getString($data, 'name');

            if (! in_array($name, ['name.traps_upper', 'name.traps_lower'], true)) {
                continue;
            }

            $id = (string) Uuid::v7();
            $ids[$name] = $id;

            $this->addSql(
                'INSERT INTO muscle_group (id, name, position, svg_ids, created_at) VALUES (:id, :name, :position, :svgIds, NOW())',
                [
                    'id' => $id,
                    'name' => $name,
                    'position' => $typeService->getInt($data, 'position'),
                    'svgIds' => json_encode($typeService->getStringArray($data, 'svgIds'), JSON_THROW_ON_ERROR),
                ],
                [
                    'position' => 'integer',
                ]
            );
        }

        return $ids;
    }
}
