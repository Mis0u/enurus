<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RegularityGoal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RegularityGoal>
 */
class RegularityGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegularityGoal::class);
    }

    /**
     * Tous les objectifs de l'utilisateur, les plus récents en premier. Leur nombre reste faible
     * (un seul en cours à la fois) : « en cours » et « historique » sont triés en PHP
     * (`RegularityGoal::isOngoingOn()`), la date de fin n'étant pas stockée.
     *
     * @return list<RegularityGoal>
     */
    public function findByOwnerNewestFirst(User $owner): array
    {
        /** @var list<RegularityGoal> */
        return $this->createQueryBuilder('g')
            ->andWhere('g.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('g.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Débloque le widget « Objectif de régularité » : il apparaît dès le premier objectif créé.
     */
    public function hasAnyForOwner(User $owner): bool
    {
        return 0 < (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
