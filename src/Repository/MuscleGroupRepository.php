<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MuscleGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MuscleGroup>
 */
class MuscleGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MuscleGroup::class);
    }

    /**
     * @return list<MuscleGroup>
     */
    public function findAllOrderedByPosition(): array
    {
        /** @var list<MuscleGroup> */
        return $this->createQueryBuilder('mg')
            ->orderBy('mg.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
