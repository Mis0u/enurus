<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Routine;
use App\Entity\User;
use App\Entity\Workout;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Workout>
 */
class WorkoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Workout::class);
    }

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('count(w.id)')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Séances loggées depuis `$since`, tous utilisateurs confondus — signal d'activité pour le
     * dashboard admin (inscriptions ≠ usage réel). `$excludedOwnerIds` exclut les comptes admin,
     * même mécanisme que `UserRepository::excludingAdminsQueryBuilder()` mais appliqué ici via
     * l'association `owner` plutôt que dupliqué en DQL.
     *
     * @param list<Uuid> $excludedOwnerIds
     */
    public function countPerformedSince(\DateTimeImmutable $since, array $excludedOwnerIds = []): int
    {
        $qb = $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.performedAt >= :since')
            ->setParameter('since', $since);

        if ([] !== $excludedOwnerIds) {
            $qb->andWhere('w.owner NOT IN (:excludedOwnerIds)')->setParameter('excludedOwnerIds', $excludedOwnerIds);
        }

        /** @var int */
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Date de la séance la plus récente de l'utilisateur — sert à déterminer la borne du jour pour
     * le filtre "Dernière journée" du widget Séance, sans charger l'entité complète (comparé à
     * l'ancien findLatestByUser(), cf. historique git). Requête id/date only, pas de JOIN sur une
     * collection, setMaxResults safe (règle CLAUDE.md).
     */
    public function findLastPerformedAtByUser(User $user): ?\DateTimeImmutable
    {
        /** @var array{performedAt: \DateTimeImmutable}|null $row */
        $row = $this->createQueryBuilder('w')
            ->select('w.performedAt')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('w.performedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);

        return $row['performedAt'] ?? null;
    }

    /**
     * @param array{type?: string, value?: DateTimeImmutable, routine?: 'free'|Routine} $filters
     */
    public function findByUserPaginated(User $user, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('w')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('w.performedAt', 'DESC');

        [$start, $end] = $this->resolveDateRange($filters);

        if (null !== $start && null !== $end) {
            $qb->andWhere('w.performedAt BETWEEN :start AND :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        // Filtre indépendant du filtre de période (combinable, pas exclusif) : "quelle routine"
        // et "quelle période" sont deux axes différents.
        $routineFilter = $filters['routine'] ?? null;

        if ('free' === $routineFilter) {
            $qb->andWhere('w.routine IS NULL');
        } elseif ($routineFilter instanceof Routine) {
            $qb->andWhere('w.routine = :routine')
                ->setParameter('routine', $routineFilter);
        }

        return $qb;
    }

    /**
     * @param array{type?: string, value?: DateTimeImmutable} $filters
     * @return array{DateTimeImmutable|null, DateTimeImmutable|null}
     */
    private function resolveDateRange(array $filters): array
    {
        $type = $filters['type'] ?? null;
        $now = new DateTimeImmutable();

        return match ($type) {
            'week' => [
                $now->modify('monday this week')->setTime(0, 0, 0),
                $now->modify('sunday this week')->setTime(23, 59, 59),
            ],
            'month' => [
                $now->modify('first day of this month')->setTime(0, 0, 0),
                $now->modify('last day of this month')->setTime(23, 59, 59),
            ],
            'date' => isset($filters['value']) ? [
                DateTimeImmutable::createFromInterface($filters['value'])->setTime(0, 0, 0),
                DateTimeImmutable::createFromInterface($filters['value'])->setTime(23, 59, 59),
            ] : [null, null],
            default => [null, null],
        };
    }
}
