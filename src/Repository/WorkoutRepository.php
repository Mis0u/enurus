<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\Workout;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

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
     * Charge le workout le plus récent avec ses workoutExercises et exercises pré-jointés.
     * Deux requêtes : la première isole l'ID (setMaxResults safe sans JOIN collection),
     * la seconde charge les collections sans limite (règle CLAUDE.md).
     */
    public function findLatestByUser(User $user): ?Workout
    {
        // Step 1 : ID uniquement — pas de JOIN sur une collection, setMaxResults safe
        $row = $this->createQueryBuilder('w')
            ->select('w.id')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('w.performedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);

        if (! is_array($row) || ! isset($row['id'])) {
            return null;
        }

        // Step 2 : chargement complet avec eager join des collections (sans setMaxResults)
        /** @var Workout|null $workout */
        $workout = $this->createQueryBuilder('w')
            ->leftJoin('w.workoutExercises', 'we')
            ->addSelect('we')
            ->leftJoin('we.exercise', 'e')
            ->addSelect('e')
            ->leftJoin('e.exerciseMuscles', 'em')
            ->addSelect('em')
            ->leftJoin('em.muscleGroup', 'mg')
            ->addSelect('mg')
            ->leftJoin('we.exerciseSets', 'es')
            ->addSelect('es')
            ->andWhere('w.id = :id')
            ->setParameter('id', $row['id'])
            ->orderBy('we.position', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();

        return $workout;
    }

    /**
     * @param array{type?: string, value?: DateTimeImmutable} $filters
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
