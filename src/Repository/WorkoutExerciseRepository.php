<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExerciseSet;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
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
            ->addSelect(
                sprintf(
                    '(SELECT SUM(s.weight * s.reps) FROM %s s WHERE s.workoutExercise = we) as tonnage',
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
