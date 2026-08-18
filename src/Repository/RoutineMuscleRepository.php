<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Routine;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use Doctrine\ORM\AbstractQuery;

/**
 * Svg ids sollicités par une routine — pendant de WorkoutMuscleRepository::findSvgIdsByWorkoutIds,
 * mais calculé sur les exercices configurés de la routine (RoutineExercise), pas sur un historique
 * de séances exécutées.
 */
class RoutineMuscleRepository
{
    public function __construct(
        private readonly RoutineExerciseRepository $routineExerciseRepository,
    ) {
    }

    /**
     * @return array{primary: list<string>, secondary: list<string>}
     */
    public function findSvgIdsByRoutine(Routine $routine): array
    {
        /** @var array<int, array{svgIds: mixed, muscleType: mixed}> $rows */
        $rows = $this->routineExerciseRepository->createQueryBuilder('re')
            ->select('mg.svgIds as svgIds', 'em.type as muscleType')
            ->join('re.exercise', 'e')
            ->join('e.exerciseMuscles', 'em')
            ->join('em.muscleGroup', 'mg')
            ->andWhere('re.routine = :routine')
            ->setParameter('routine', $routine)
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

            if (MuscleTypeEnum::PRIMARY->value === $this->resolveMuscleType($row['muscleType'])) {
                $primary = array_merge($primary, $svgIds);
            } else {
                $secondary = array_merge($secondary, $svgIds);
            }
        }

        $primary = array_values(array_unique($primary));
        $secondary = array_values(array_diff(array_unique($secondary), $primary));

        return [
            MuscleTypeEnum::PRIMARY->value => $primary,
            MuscleTypeEnum::SECONDARY->value => $secondary,
        ];
    }

    private function resolveMuscleType(mixed $type): string
    {
        if ($type instanceof \BackedEnum) {
            return (string) $type->value;
        }

        /** @var string $type */
        return $type;
    }
}
