<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\RoutineExercise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoutineExercise>
 */
final class RoutineExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoutineExercise::class);
    }

    /**
     * @return list<RoutineExercise>
     */
    public function findByExercise(Exercise $exercise): array
    {
        /** @var list<RoutineExercise> */
        return $this->createQueryBuilder('re')
            ->where('re.exercise = :exercise')
            ->setParameter('exercise', $exercise)
            ->getQuery()
            ->getResult();
    }
}
