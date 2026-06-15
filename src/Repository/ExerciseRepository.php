<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exercise>
 */
final class ExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exercise::class);
    }

    /**
     * Returns all public exercises.
     * No SQL ordering — names are translation keys, sorting must happen
     * after translation in memory.
     *
     * @return list<Exercise>
     */
    public function findPublicExercises(): array
    {
        /** @var list<Exercise> */
        return $this->createQueryBuilder('e')
            ->where('e.isPublic = :public')
            ->setParameter('public', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all custom exercises created by a specific user.
     *
     * @return list<Exercise>
     */
    public function findCustomExercisesByUser(User $user): array
    {
        /** @var list<Exercise> */
        return $this->createQueryBuilder('e')
            ->where('e.isPublic = :public')
            ->andWhere('e.owner = :owner')
            ->setParameter('public', false)
            ->setParameter('owner', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all exercises available for a user:
     * public exercises + the user's own custom exercises.
     *
     * @return list<Exercise>
     */
    public function findAvailableForUser(User $user): array
    {
        /** @var list<Exercise> */
        return $this->createQueryBuilder('e')
            ->leftJoin('e.exerciseMuscles', 'em')
            ->addSelect('em')
            ->leftJoin('em.muscleGroup', 'mg')
            ->addSelect('mg')
            ->where('e.isPublic = :public')
            ->orWhere('e.owner = :owner')
            ->setParameter('public', true)
            ->setParameter('owner', $user)
            ->getQuery()
            ->getResult();
    }
}
