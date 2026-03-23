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
class ExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exercise::class);
    }

    /**
     * @return Exercise[]
     */
    public function findAvailableForUser(User $user): array
    {
        /** @var Exercise[] $result */
        $result = $this->createQueryBuilder('e')
            ->addSelect('em', 'mg')
            ->leftJoin('e.exerciseMuscles', 'em')
            ->leftJoin('em.muscleGroup', 'mg')
            ->where('e.isPublic = true')
            ->orWhere('(e.owner = :user AND e.isPublic = false)')
            ->setParameter('user', $user)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
