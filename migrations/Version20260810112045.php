<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\DataFixtures\Service\Type\TypeService;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

/**
 * Resync every public exercise's primary/secondary muscles against Exercises.json.
 *
 * The reference exercises seeded by Version20260810061845 had incomplete secondary muscle
 * lists (missing common synergists — e.g. pull-ups never listed the rear delts or lower/middle
 * traps). Exercises.json is now the corrected, curated source of truth (reviewed exercise by
 * exercise); this migration deletes and re-inserts each public exercise's ExerciseMuscle rows to
 * match it exactly. Naturally idempotent — re-running it is a harmless no-op resync.
 */
final class Version20260810112045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Resync public exercises' primary/secondary muscles against Exercises.json";
    }

    public function up(Schema $schema): void
    {
        $muscleGroupIds = $this->fetchMuscleGroupIds();
        $typeService = new TypeService();

        foreach ($this->loadExercises() as $data) {
            if (! $typeService->getBool($data, 'isPublic')) {
                continue;
            }

            $exerciseName = $typeService->getString($data, 'name');
            $exerciseId = $this->connection->fetchOne(
                'SELECT id FROM exercise WHERE name = :name',
                [
                    'name' => $exerciseName,
                ]
            );

            if (! is_string($exerciseId)) {
                continue;
            }

            $this->addSql('DELETE FROM exercise_muscle WHERE exercise_id = :exerciseId', [
                'exerciseId' => $exerciseId,
            ]);

            $this->insertExerciseMuscles($exerciseId, $typeService->getStringArray($data, 'primary'), MuscleTypeEnum::PRIMARY->value, $muscleGroupIds);
            $this->insertExerciseMuscles($exerciseId, $typeService->getStringArray($data, 'secondary'), MuscleTypeEnum::SECONDARY->value, $muscleGroupIds);
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }

    /**
     * @return array<string, string> muscle group name => uuid
     */
    private function fetchMuscleGroupIds(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, name FROM muscle_group');
        $ids = [];

        foreach ($rows as $row) {
            $id = $row['id'];
            $name = $row['name'];

            if (! is_string($id) || ! is_string($name)) {
                throw new \LogicException('Unexpected non-string id/name for a muscle_group row.');
            }

            $ids[$name] = $id;
        }

        return $ids;
    }

    /**
     * @param string[] $muscleNames
     * @param array<string, string> $muscleGroupIds
     */
    private function insertExerciseMuscles(string $exerciseId, array $muscleNames, string $type, array $muscleGroupIds): void
    {
        foreach ($muscleNames as $muscleName) {
            if (! isset($muscleGroupIds[$muscleName])) {
                continue;
            }

            $this->addSql(
                'INSERT INTO exercise_muscle (id, type, exercise_id, muscle_group_id) VALUES (:id, :type, :exerciseId, :muscleGroupId)',
                [
                    'id' => (string) Uuid::v7(),
                    'type' => $type,
                    'exerciseId' => $exerciseId,
                    'muscleGroupId' => $muscleGroupIds[$muscleName],
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadExercises(): array
    {
        $path = \sprintf('%s/src/DataFixtures/JSON/Exercises.json', \dirname(__DIR__));
        $content = file_get_contents($path);

        if (false === $content) {
            throw new \RuntimeException(\sprintf('Impossible de lire le fichier : %s', $path));
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new \RuntimeException(\sprintf('Le fichier JSON ne contient pas un tableau : %s', $path));
        }

        /** @var array<int, array<string, mixed>> $data */
        return $data;
    }
}
