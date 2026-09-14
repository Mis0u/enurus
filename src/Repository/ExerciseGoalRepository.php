<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExerciseGoal>
 */
class ExerciseGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciseGoal::class);
    }

    /**
     * Plusieurs objectifs peuvent exister pour un même (owner, exercise) au fil du temps — les
     * atteints restent en historique, jamais purgés. Voir `App\Service\Goal\GoalStateResolver`
     * pour déterminer lequel est l'objectif actif.
     *
     * @return array<ExerciseGoal>
     */
    public function findAllByOwnerAndExercise(User $owner, Exercise $exercise): array
    {
        /** @var array<ExerciseGoal> */
        return $this->createQueryBuilder('g')
            ->andWhere('g.owner = :owner')
            ->andWhere('g.exercise = :exercise')
            ->setParameter('owner', $owner)
            ->setParameter('exercise', $exercise)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<ExerciseGoal>
     */
    public function findByOwner(User $owner): array
    {
        /** @var array<ExerciseGoal> */
        return $this->createQueryBuilder('g')
            ->andWhere('g.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getResult();
    }
}
