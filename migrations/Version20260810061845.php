<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\DataFixtures\Service\Type\TypeService;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

/**
 * Seed reference MuscleGroup and public Exercise data.
 *
 * MuscleGroupFixtures/ExerciseFixtures only run in dev/test — this data never existed in prod.
 * Reads the same JSON files as the fixtures (single source of truth), guarded by skipIf so it
 * stays a no-op on any dev/test database where fixtures already loaded this data.
 */
final class Version20260810061845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed reference MuscleGroup and public Exercise data (missing in prod, fixtures are dev-only)';
    }

    public function up(Schema $schema): void
    {
        $muscleGroupCount = $this->connection->fetchOne('SELECT COUNT(*) FROM muscle_group');

        if (! is_numeric($muscleGroupCount)) {
            throw new \LogicException('Impossible de déterminer le nombre de muscle groups existants.');
        }

        $this->skipIf(0 < (int) $muscleGroupCount, 'Muscle groups already seeded (dev fixtures already loaded)');

        $muscleGroupIds = $this->insertMuscleGroups();
        $this->insertPublicExercises($muscleGroupIds);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM exercise_muscle WHERE exercise_id IN (SELECT id FROM exercise WHERE owner_id IS NULL AND created_by_id IS NULL)');
        $this->addSql('DELETE FROM exercise WHERE owner_id IS NULL AND created_by_id IS NULL');
        $this->addSql('DELETE FROM muscle_group WHERE created_by_id IS NULL');
    }

    /**
     * @return array<string, string> nom du muscle group => uuid généré
     */
    private function insertMuscleGroups(): array
    {
        $typeService = new TypeService();
        $muscleGroups = $this->loadJson('Muscle-groups.json');
        $ids = [];

        foreach ($muscleGroups as $data) {
            $name = $typeService->getString($data, 'name');
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

    /**
     * @param array<string, string> $muscleGroupIds
     */
    private function insertPublicExercises(array $muscleGroupIds): void
    {
        $typeService = new TypeService();
        $exercises = $this->loadJson('Exercises.json');

        foreach ($exercises as $data) {
            if (! $typeService->getBool($data, 'isPublic')) {
                continue;
            }

            $exerciseId = (string) Uuid::v7();

            $this->addSql(
                'INSERT INTO exercise (id, name, description, is_public, archived, created_at) VALUES (:id, :name, :description, TRUE, FALSE, NOW())',
                [
                    'id' => $exerciseId,
                    'name' => $typeService->getString($data, 'name'),
                    'description' => $typeService->getString($data, 'description'),
                ]
            );

            $this->insertExerciseMuscles($exerciseId, $typeService->getStringArray($data, 'primary'), 'PRIMARY', $muscleGroupIds);
            $this->insertExerciseMuscles($exerciseId, $typeService->getStringArray($data, 'secondary'), 'SECONDARY', $muscleGroupIds);
        }
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
    private function loadJson(string $fileName): array
    {
        $path = \sprintf('%s/src/DataFixtures/JSON/%s', \dirname(__DIR__), $fileName);
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
