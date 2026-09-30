<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\YearInReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<YearInReview>
 */
class YearInReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, YearInReview::class);
    }

    public function existsForOwnerAndYear(User $owner, int $year): bool
    {
        return 0 < (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.owner = :owner')
            ->andWhere('r.year = :year')
            ->setParameter('owner', $owner)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
