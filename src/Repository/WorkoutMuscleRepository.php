<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;

/**
 * Requêtes de groupement par muscle sollicité (SVG, séries, dernière sollicitation) sur un
 * ensemble de workouts — extrait de WorkoutRepository (split SRP, voir CLAUDE.md TODO) car ces
 * 4 méthodes partagent la même jointure de base (muscleGroupJoinQueryBuilder) et ne concernent
 * pas la même responsabilité que la pagination/le tonnage/les comptages bruts.
 */
class WorkoutMuscleRepository
{
    public function __construct(
        private readonly WorkoutRepository $workoutRepository,
    ) {
    }

    /**
     * @param string[] $workoutIds
     * @return array{primary: list<string>, secondary: list<string>}
     */
    public function findSvgIdsByWorkoutIds(array $workoutIds): array
    {
        if ([] === $workoutIds) {
            return [
                'primary' => [],
                'secondary' => [],
            ];
        }

        /** @var array<int, array{svgIds: mixed, muscleType: mixed}> $rows */
        $rows = $this->muscleGroupJoinQueryBuilder()
            ->select('mg.svgIds as svgIds', 'em.type as muscleType')
            ->andWhere('w.id IN (:ids)')
            ->setParameter('ids', $workoutIds)
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        $primary = [];
        $secondary = [];

        foreach ($rows as $row) {
            $svgIds = $row['svgIds'];
            if (! is_array($svgIds)) {
                continue;
            }
            /** @var list<string> $svgIds */

            $type = $row['muscleType'];
            $typeValue = $this->resolveMuscleType($type);

            if (MuscleTypeEnum::PRIMARY->value === $typeValue) {
                $primary = array_merge($primary, $svgIds);
            } else {
                $secondary = array_merge($secondary, $svgIds);
            }
        }

        $primary = array_values(array_unique($primary));
        $secondary = array_values(array_diff(array_unique($secondary), $primary));

        return [
            'primary' => $primary,
            'secondary' => $secondary,
        ];
    }

    /**
     * Nombre de séries par groupe musculaire sollicité (primaire OU secondaire confondus),
     * sur un ensemble de workouts, avec le détail primaire/secondaire. Un groupe non sollicité
     * n'apparaît pas dans le résultat.
     *
     * @param string[] $workoutIds
     * @return array<int, array{id: string, name: string, sets: int, primarySets: int, secondarySets: int}>
     */
    public function findMuscleGroupSetCountsByWorkoutIds(array $workoutIds): array
    {
        if ([] === $workoutIds) {
            return [];
        }

        /** @var array<int, array{muscleGroupId: mixed, muscleGroupName: mixed, muscleType: mixed}> $rows */
        $rows = $this->muscleGroupJoinQueryBuilder()
            ->select('mg.id as muscleGroupId', 'mg.name as muscleGroupName', 'em.type as muscleType')
            ->join('we.exerciseSets', 'es')
            ->andWhere('w.id IN (:ids)')
            ->setParameter('ids', $workoutIds)
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        /** @var array<string, array{id: string, name: string, sets: int, primarySets: int, secondarySets: int}> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            /** @var \Stringable|string $muscleGroupId */
            $muscleGroupId = $row['muscleGroupId'];
            $id = (string) $muscleGroupId;

            /** @var string $muscleGroupName */
            $muscleGroupName = $row['muscleGroupName'];

            $type = $row['muscleType'];
            $typeValue = $this->resolveMuscleType($type);

            if (! isset($grouped[$id])) {
                $grouped[$id] = [
                    'id' => $id,
                    'name' => $muscleGroupName,
                    'sets' => 0,
                    'primarySets' => 0,
                    'secondarySets' => 0,
                ];
            }

            $grouped[$id]['sets']++;

            if (MuscleTypeEnum::PRIMARY->value === $typeValue) {
                $grouped[$id]['primarySets']++;
            } else {
                $grouped[$id]['secondarySets']++;
            }
        }

        return array_values($grouped);
    }

    /**
     * Date de la dernière séance ayant sollicité chaque groupe musculaire (primaire ou
     * secondaire), sur tout l'historique de l'utilisateur — une seule requête, le MAX par
     * groupe est calculé en PHP plutôt qu'en DQL (évite l'ambiguïté d'hydratation d'un
     * agrégat MAX() sur un champ datetime_immutable, même philosophie que le regroupement
     * par semaine/mois du widget Tonnage).
     *
     * @return array<string, \DateTimeImmutable> groupe musculaire (id) => date de dernière sollicitation
     */
    public function findLastSolicitationDatesByMuscleGroup(User $user): array
    {
        /** @var array<int, array{muscleGroupId: mixed, performedAt: mixed}> $rows */
        $rows = $this->muscleGroupJoinQueryBuilder()
            ->select('mg.id as muscleGroupId', 'w.performedAt as performedAt')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        /** @var array<string, \DateTimeImmutable> $result */
        $result = [];

        foreach ($rows as $row) {
            /** @var \Stringable|string $muscleGroupId */
            $muscleGroupId = $row['muscleGroupId'];
            $id = (string) $muscleGroupId;

            /** @var \DateTimeImmutable $performedAt */
            $performedAt = $row['performedAt'];

            if (! isset($result[$id]) || $performedAt > $result[$id]) {
                $result[$id] = $performedAt;
            }
        }

        return $result;
    }

    /**
     * @param string[] $workoutIds
     * @return array<string, array<int, array{name: string, type: string}>>
     */
    public function findMusclesByWorkoutIds(array $workoutIds): array
    {
        if (empty($workoutIds)) {
            return [];
        }

        $rows = $this->fetchMuscleRows($workoutIds);
        $grouped = $this->deduplicateMuscles($rows);

        return $this->sortMusclesByType($grouped);
    }

    /**
     * DQL hydrate `em.type` tantôt en enum backed, tantôt en scalaire selon le contexte de
     * sélection — normalisation en un point unique plutôt que dupliquée à chaque appelant.
     */
    private function resolveMuscleType(mixed $type): string
    {
        if ($type instanceof \BackedEnum) {
            return (string) $type->value;
        }

        /** @var string $type */
        return $type;
    }

    /**
     * @param string[] $workoutIds
     * @return array<int, array{workoutId: mixed, muscleName: string, musclePosition: mixed, muscleType: mixed}>
     */
    private function fetchMuscleRows(array $workoutIds): array
    {
        /** @var array<int, array{workoutId: mixed, muscleName: string, musclePosition: mixed, muscleType: mixed}> $rows */
        $rows = $this->muscleGroupJoinQueryBuilder()
            ->select(
                'w.id as workoutId',
                'mg.name as muscleName',
                'mg.position as musclePosition',
                'em.type as muscleType'
            )
            ->andWhere('w.id IN (:ids)')
            ->setParameter('ids', $workoutIds)
            ->orderBy('mg.position', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Base commune (jointures exercice → muscles → groupe musculaire) réutilisée par toutes les
     * requêtes agrégeant par groupe musculaire — évite de répéter les 4 mêmes jointures dans
     * chaque méthode.
     */
    private function muscleGroupJoinQueryBuilder(): QueryBuilder
    {
        return $this->workoutRepository->createQueryBuilder('w')
            ->join('w.workoutExercises', 'we')
            ->join('we.exercise', 'e')
            ->join('e.exerciseMuscles', 'em')
            ->join('em.muscleGroup', 'mg');
    }

    /**
     * @param array<int, array{workoutId: mixed, muscleName: string, musclePosition: mixed, muscleType: mixed}> $rows
     * @return array<string, array<string, string>>
     */
    private function deduplicateMuscles(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            /** @var string|\Stringable $workoutId */
            $workoutId = $row['workoutId'];

            /** @var mixed $type */
            $type = $row['muscleType'];

            $wid = (string) $workoutId;
            $name = $row['muscleName'];
            $typeValue = $this->resolveMuscleType($type);

            if (! isset($grouped[$wid][$name])) {
                $grouped[$wid][$name] = $typeValue;
            } elseif ($typeValue === MuscleTypeEnum::PRIMARY->value) {
                $grouped[$wid][$name] = MuscleTypeEnum::PRIMARY->value;
            }
        }

        return $grouped;
    }

    /**
     * @param array<string, array<string, string>> $grouped
     * @return array<string, array<int, array{name: string, type: string}>>
     */
    private function sortMusclesByType(array $grouped): array
    {
        $result = [];

        foreach ($grouped as $wid => $muscles) {
            $primaries = [];
            $secondaries = [];

            foreach ($muscles as $name => $type) {
                if ($type === MuscleTypeEnum::PRIMARY->value) {
                    $primaries[] = [
                        'name' => $name,
                        'type' => MuscleTypeEnum::PRIMARY->value,
                    ];
                } else {
                    $secondaries[] = [
                        'name' => $name,
                        'type' => MuscleTypeEnum::SECONDARY->value,
                    ];
                }
            }

            $result[$wid] = array_merge($primaries, $secondaries);
        }

        return $result;
    }
}
