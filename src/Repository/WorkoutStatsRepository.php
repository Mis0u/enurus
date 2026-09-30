<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\Workout\WorkoutMoodEnum;
use DateTimeImmutable;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

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
     * Propriétaires ayant créé au moins une séance depuis la date donnée pour chacun, en une seule
     * requête. `Workout` n'a pas de date de création : elle est lue dans son identifiant UUIDv7,
     * dont l'ordre suit celui des insertions — jamais `performedAt`, date métier saisie à la main
     * (une séance saisie aujourd'hui peut être datée du mois dernier).
     *
     * @param array<string, DateTimeImmutable> $sinceByOwnerId clé = identifiant du propriétaire
     * @return list<string>
     */
    public function findOwnerIdsWithWorkoutCreatedSince(array $sinceByOwnerId): array
    {
        if ([] === $sinceByOwnerId) {
            return [];
        }

        $queryBuilder = $this->workoutRepository->createQueryBuilder('w')
            ->select('DISTINCT IDENTITY(w.owner) AS ownerId');
        $conditions = $queryBuilder->expr()->orX();
        $index = 0;

        foreach ($sinceByOwnerId as $ownerId => $since) {
            $conditions->add(\sprintf('w.owner = :owner%1$d AND w.id >= :boundary%1$d', $index));
            $queryBuilder
                ->setParameter('owner' . $index, Uuid::fromString($ownerId), UuidType::NAME)
                ->setParameter('boundary' . $index, $this->firstUuidV7At($since), UuidType::NAME);
            ++$index;
        }

        /** @var list<array{ownerId: string}> $rows */
        $rows = $queryBuilder->andWhere($conditions)->getQuery()->getScalarResult();

        return array_map(static fn (array $row): string => $row['ownerId'], $rows);
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

    /**
     * Une ligne par séance (date, nombre de séries, répétitions) sur la plage donnée — le widget
     * Comparaison additionne ensuite ses six périodes en mémoire plutôt que de lancer une requête
     * d'agrégat par période.
     *
     * @return list<array{performedAt: \DateTimeImmutable, sets: int, reps: int}>
     */
    public function findSetAndRepTotalsPerWorkout(User $user, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        /** @var list<array{performedAt: \DateTimeImmutable, sets: mixed, reps: mixed}> $rows */
        $rows = $this->workoutRepository->createQueryBuilder('w')
            ->select('w.performedAt AS performedAt', 'COUNT(es.id) AS sets', 'COALESCE(SUM(es.reps), 0) AS reps')
            ->leftJoin('w.workoutExercises', 'we')
            ->leftJoin('we.exerciseSets', 'es')
            ->andWhere('w.owner = :user')
            ->andWhere('w.performedAt >= :start')
            ->andWhere('w.performedAt <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('w.id', 'w.performedAt')
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        return array_map(static fn (array $row): array => [
            'performedAt' => $row['performedAt'],
            'sets' => is_numeric($row['sets']) ? (int) $row['sets'] : 0,
            'reps' => is_numeric($row['reps']) ? (int) $row['reps'] : 0,
        ], $rows);
    }

    /**
     * `$excludeWorkoutId` permet à l'édition d'une séance de vérifier les doublons sans compter la
     * séance en cours d'édition elle-même (sinon toute date déjà utilisée par CE workout serait
     * systématiquement signalée comme doublon).
     */
    public function countByUserAndDate(
        User $user,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?Uuid $excludeWorkoutId = null,
    ): int {
        $qb = $this->workoutRepository->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.owner = :user')
            ->andWhere('w.performedAt >= :start')
            ->andWhere('w.performedAt <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if (null !== $excludeWorkoutId) {
            $qb->andWhere('w.id != :excludeId')
                ->setParameter('excludeId', $excludeWorkoutId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
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

    /**
     * Exercices les plus pratiqués par l'utilisateur depuis `$since`, classés par nombre de
     * séances (une séance compte une fois, quel que soit son nombre de séries), à égalité par
     * séance la plus récente — sert à la section « Tes habituels » du sélecteur d'exercices.
     * `setMaxResults()` sans risque ici : agrégat par exercice, aucune collection jointe.
     *
     * @return list<string> identifiants d'exercices
     */
    public function findMostFrequentExerciseIdsSince(User $user, DateTimeImmutable $since, int $limit): array
    {
        /** @var list<array{exerciseId: \Stringable|string}> $rows */
        $rows = $this->mostFrequentExercisesQuery($user, $since, $limit)
            ->select('IDENTITY(we.exercise) AS exerciseId')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): string => (string) $row['exerciseId'], $rows);
    }

    /**
     * Même classement que `findMostFrequentExerciseIdsSince()`, borné des deux côtés et avec le nom
     * de l'exercice — top exercices du résumé annuel, qui fige le nom (l'exercice peut être
     * supprimé ensuite).
     *
     * @return list<array{name: string, isPublic: bool, workoutCount: int}>
     */
    public function findMostFrequentExercisesInRange(User $user, DateTimeImmutable $start, DateTimeImmutable $end, int $limit): array
    {
        /** @var list<array{name: string, isPublic: bool, workoutCount: numeric}> $rows */
        $rows = $this->mostFrequentExercisesQuery($user, $start, $limit)
            ->select('e.name AS name', 'e.isPublic AS isPublic', 'COUNT(DISTINCT w.id) AS workoutCount')
            ->join('we.exercise', 'e')
            ->andWhere('w.performedAt <= :end')
            ->setParameter('end', $end)
            ->addGroupBy('e.name', 'e.isPublic')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            ...$row,
            'workoutCount' => (int) $row['workoutCount'],
        ], $rows);
    }

    /**
     * Nombre de séances par humeur saisie sur la plage (les séances sans humeur sont ignorées).
     *
     * @return array<string, int> valeur de `WorkoutMoodEnum` => nombre de séances
     */
    public function countMoodsInRange(User $user, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        /** @var list<array{mood: WorkoutMoodEnum, workoutCount: numeric}> $rows */
        $rows = $this->workoutRepository->createQueryBuilder('w')
            ->select('w.mood AS mood', 'COUNT(w.id) AS workoutCount')
            ->andWhere('w.owner = :user')
            ->andWhere('w.mood IS NOT NULL')
            ->andWhere('w.performedAt >= :start')
            ->andWhere('w.performedAt <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('w.mood')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['mood']->value] = (int) $row['workoutCount'];
        }

        return $counts;
    }

    /**
     * Agrégat par exercice, aucune collection jointe : `setMaxResults()` sans risque.
     */
    private function mostFrequentExercisesQuery(User $user, DateTimeImmutable $since, int $limit): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->from(WorkoutExercise::class, 'we')
            ->join('we.workout', 'w')
            ->andWhere('w.owner = :user')
            ->andWhere('w.performedAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->groupBy('we.exercise')
            ->orderBy('COUNT(DISTINCT w.id)', 'DESC')
            ->addOrderBy('MAX(w.performedAt)', 'DESC')
            ->setMaxResults($limit);
    }

    /**
     * Plus petit UUIDv7 possible à cet instant : les 48 premiers bits portent le timestamp en
     * millisecondes, tout le reste (hors version et variante) à zéro.
     */
    private function firstUuidV7At(DateTimeImmutable $instant): Uuid
    {
        $timestampHex = str_pad(dechex((int) $instant->format('Uv')), 12, '0', \STR_PAD_LEFT);

        return Uuid::fromString(\sprintf('%s-%s-7000-8000-000000000000', substr($timestampHex, 0, 8), substr($timestampHex, 8)));
    }
}
