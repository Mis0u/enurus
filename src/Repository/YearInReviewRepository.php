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
     * Résumés éligibles de `$year` dont l'email d'annonce reste à envoyer, à des comptes vérifiés,
     * qui l'acceptent et ne sont pas en cours de suppression.
     *
     * @return list<string>
     */
    public function findIdsAwaitingAnnouncement(int $year): array
    {
        /** @var list<array{id: \Stringable|string}> $rows */
        $rows = $this->createQueryBuilder('r')
            ->select('r.id')
            ->join('r.owner', 'o')
            ->andWhere('r.year = :year')
            ->andWhere('r.snapshotData IS NOT NULL')
            ->andWhere('r.emailedAt IS NULL')
            ->andWhere('o.emailOnYearInReview = true')
            ->andWhere('o.isVerified = true')
            ->andWhere('o.deletionRequestedAt IS NULL')
            ->setParameter('year', $year)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => (string) $row['id'], $rows);
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
