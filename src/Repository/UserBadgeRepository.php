<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserBadge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserBadge>
 */
class UserBadgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBadge::class);
    }

    /**
     * @return list<UserBadge>
     */
    public function findByOwner(User $owner): array
    {
        /** @var list<UserBadge> */
        return $this->createQueryBuilder('b')
            ->andWhere('b.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('b.unlockedAt', 'DESC')
            ->addOrderBy('b.tier', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Badges rattachés à plusieurs séances en une seule requête (liste des séances, page de détail).
     *
     * @param list<string> $workoutIds
     * @return list<UserBadge>
     */
    public function findByWorkoutIds(array $workoutIds): array
    {
        if ([] === $workoutIds) {
            return [];
        }

        /** @var list<UserBadge> */
        return $this->createQueryBuilder('b')
            ->addSelect('w')
            ->join('b.workout', 'w')
            ->andWhere('w.id IN (:workoutIds)')
            ->setParameter('workoutIds', $workoutIds)
            ->orderBy('b.family', 'ASC')
            ->addOrderBy('b.tier', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
