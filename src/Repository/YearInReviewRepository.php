<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\YearInReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
        return 0 < (int) $this->ownerAndYearQuery($owner, $year)
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Point bleu de la navigation : résumé éligible (snapshot présent) jamais ouvert.
     */
    public function existsUnseenEligibleForOwnerAndYear(User $owner, int $year): bool
    {
        return 0 < (int) $this->ownerAndYearQuery($owner, $year)
            ->select('COUNT(r.id)')
            ->andWhere('r.snapshotData IS NOT NULL')
            ->andWhere('r.seenAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Résumé ouvrable en écrans : éligible (snapshot présent) ; sinon null, la page répond 404.
     */
    public function findEligibleByOwnerAndYear(User $owner, int $year): ?YearInReview
    {
        /** @var YearInReview|null */
        return $this->ownerAndYearQuery($owner, $year)
            ->andWhere('r.snapshotData IS NOT NULL')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Résumés de l'utilisateur jusqu'à `$latestYear` inclus, du plus récent au plus ancien.
     *
     * @return list<YearInReview>
     */
    public function findByOwnerUpToYear(User $owner, int $latestYear): array
    {
        /** @var list<YearInReview> */
        return $this->createQueryBuilder('r')
            ->andWhere('r.owner = :owner')
            ->andWhere('r.year <= :latestYear')
            ->setParameter('owner', $owner)
            ->setParameter('latestYear', $latestYear)
            ->orderBy('r.year', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function ownerAndYearQuery(User $owner, int $year): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.owner = :owner')
            ->andWhere('r.year = :year')
            ->setParameter('owner', $owner)
            ->setParameter('year', $year);
    }
}
