<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Comptages/dates/totaux exercices-séries-reps agrégés — extrait de WorkoutRepository (split
 * SRP, voir CLAUDE.md TODO), regroupés ici car hétérogènes mais tous de nature "statistique"
 * plutôt que muscles/tonnage/pagination.
 */
class WorkoutStatsRepository
{
    public function __construct(
        private readonly WorkoutRepository $workoutRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return string[]
     */
    public function findIdsByUserAndDateRange(User $user, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        /** @var array<int, array{id: mixed}> $rows */
        $rows = $this->workoutRepository->createQueryBuilder('w')
            ->select('w.id')
            ->andWhere('w.owner = :user')
            ->andWhere('w.performedAt >= :start')
            ->andWhere('w.performedAt <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        return array_map(static function (array $row): string {
            /** @var \Stringable $id */
            $id = $row['id'];

            return (string) $id;
        }, $rows);
    }

    /**
     * @return \DateTimeImmutable[]
     */
    public function findAllPerformedDatesByUser(User $user): array
    {
        /** @var array<int, array{performedAt: \DateTimeImmutable}> $rows */
        $rows = $this->workoutRepository->createQueryBuilder('w')
            ->select('w.performedAt')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        return array_map(static fn (array $row): \DateTimeImmutable => $row['performedAt'], $rows);
    }

    public function countByUserAndDate(User $user, DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        return (int) $this->workoutRepository->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.owner = :user')
            ->andWhere('w.performedAt >= :start')
            ->andWhere('w.performedAt <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Totaux exercices/séries/reps sur une plage de dates (ou tout l'historique si null).
     *
     * @return array{exercises: int, sets: int, reps: int}
     */
    public function findExerciseSetRepTotals(
        User $user,
        ?DateTimeImmutable $start = null,
        ?DateTimeImmutable $end = null,
    ): array {
        $qb = $this->workoutRepository->createQueryBuilder('w')
            ->select('COUNT(DISTINCT we.id) as exercises', 'COUNT(es.id) as sets', 'COALESCE(SUM(es.reps), 0) as reps')
            ->leftJoin('w.workoutExercises', 'we')
            ->leftJoin('we.exerciseSets', 'es')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user);

        if (null !== $start && null !== $end) {
            $qb->andWhere('w.performedAt >= :start')
                ->andWhere('w.performedAt <= :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        /** @var array<int, array{exercises: mixed, sets: mixed, reps: mixed}> $rows */
        $rows = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);

        if (! isset($rows[0])) {
            throw new \LogicException('Aggregate query must always return exactly one row.');
        }

        /** @var numeric $exercises */
        $exercises = $rows[0]['exercises'];
        /** @var numeric $sets */
        $sets = $rows[0]['sets'];
        /** @var numeric $reps */
        $reps = $rows[0]['reps'];

        return [
            'exercises' => (int) $exercises,
            'sets' => (int) $sets,
            'reps' => (int) $reps,
        ];
    }

    /**
     * @param string[] $workoutIds
     * @return array<string, int>
     */
    public function findExerciseCountByWorkoutIds(array $workoutIds): array
    {
        if (empty($workoutIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, \count($workoutIds), '?'));

        $sql = \sprintf(
            'SELECT w.id as workout_id, COUNT(we.id) as exercise_count
         FROM workout w
         INNER JOIN workout_exercise we ON we.workout_id = w.id
         WHERE w.id IN (%s)
         GROUP BY w.id',
            $placeholders
        );

        $result = [];
        $rows = $this->entityManager
            ->getConnection()
            ->executeQuery($sql, array_values($workoutIds))
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            /** @var string $workoutId */
            $workoutId = $row['workout_id'];
            /** @var numeric $exerciseCount */
            $exerciseCount = $row['exercise_count'];

            $result[$workoutId] = (int) $exerciseCount;
        }

        return $result;
    }
}
