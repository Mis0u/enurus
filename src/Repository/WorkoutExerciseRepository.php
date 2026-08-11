<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\Exercise\MeasurementType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkoutExercise>
 */
final class WorkoutExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutExercise::class);
    }

    /**
     * `poids × reps` pour un exercice `WEIGHT_REPS`, `poids` seul (comme une série à 1 rep) pour
     * un exercice `TIME` — même formule que `WorkoutTonnageRepository::TONNAGE_SUBQUERY_DQL`, dont
     * la responsabilité est différente (tonnage par séance entière, pas par workoutExercice).
     *
     * @param string[] $workoutExerciseIds
     * @return array<string, float>
     */
    public function findTonnageByWorkoutExerciseIds(array $workoutExerciseIds): array
    {
        if (empty($workoutExerciseIds)) {
            return [];
        }

        /** @var array<int, array{workoutExerciseId: mixed, tonnage: mixed}> $rows */
        $rows = $this->createQueryBuilder('we')
            ->select('we.id as workoutExerciseId')
            ->join('we.exercise', 'e')
            ->addSelect(
                sprintf(
                    "(SELECT COALESCE(SUM(
                        CASE WHEN e.measurementType = '%s' THEN s.weight * s.reps
                             ELSE s.weight
                        END
                    ), 0) FROM %s s WHERE s.workoutExercise = we) as tonnage",
                    MeasurementType::WEIGHT_REPS->value,
                    ExerciseSet::class
                )
            )
            ->andWhere('we.id IN (:ids)')
            ->setParameter('ids', $workoutExerciseIds)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            /** @var string|\Stringable $workoutExerciseId */
            $workoutExerciseId = $row['workoutExerciseId'];
            /** @var numeric|null $tonnage */
            $tonnage = $row['tonnage'];
            $result[(string) $workoutExerciseId] = null !== $tonnage ? (float) $tonnage : 0.0;
        }

        return $result;
    }

    public function countByExercise(Exercise $exercise): int
    {
        return (int) $this->createQueryBuilder('we')
            ->select('COUNT(we.id)')
            ->where('we.exercise = :exercise')
            ->setParameter('exercise', $exercise)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<WorkoutExercise>
     */
    public function findWithExercisesAndSets(Workout $workout): array
    {
        /** @var array<WorkoutExercise> $result */
        $result = $this->createQueryBuilder('we')
            ->select('we', 'e', 'em', 'mg', 'es')
            ->join('we.exercise', 'e')
            ->join('e.exerciseMuscles', 'em')
            ->join('em.muscleGroup', 'mg')
            ->join('we.exerciseSets', 'es')
            ->where('we.workout = :workout')
            ->setParameter('workout', $workout)
            ->orderBy('we.position', 'ASC')
            ->addOrderBy('es.position', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
