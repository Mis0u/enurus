<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExerciseSet>
 */
class ExerciseSetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciseSet::class);
    }

    /**
     * @param array<Exercise> $exercises
     * @return array<string, float>
     */
    public function findMaxWeightPerExercise(User $user, array $exercises): array
    {
        if (empty($exercises)) {
            return [];
        }

        /** @var array<int, array{exerciseId: mixed, maxWeight: mixed}> $results */
        $results = $this->createQueryBuilder('es')
            ->select('e.id AS exerciseId, MAX(es.weight) AS maxWeight')
            ->join('es.workoutExercise', 'we')
            ->join('we.exercise', 'e')
            ->join('we.workout', 'w')
            ->where('w.owner = :user')
            ->andWhere('e IN (:exercises)')
            ->setParameter('user', $user)
            ->setParameter('exercises', $exercises)
            ->groupBy('e.id')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($results as $row) {
            /** @var string|\Stringable $exerciseId */
            $exerciseId = $row['exerciseId'];
            /** @var numeric|null $maxWeight */
            $maxWeight = $row['maxWeight'];
            $map[(string) $exerciseId] = null !== $maxWeight ? (float) $maxWeight : 0.0;
        }

        return $map;
    }
}
