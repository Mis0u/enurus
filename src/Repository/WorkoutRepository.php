<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\Workout;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
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
    /**
     * Sous-requête DQL de tonnage réutilisée par toutes les requêtes agrégeant le tonnage
     * (poids × reps) d'une séance — évite de la dupliquer dans chaque méthode.
     */
    private const string TONNAGE_SUBQUERY_DQL = '(SELECT COALESCE(SUM(s.weight * s.reps), 0)
              FROM App\Entity\ExerciseSet s
              JOIN s.workoutExercise we2
              WHERE we2.workout = w
            ) as tonnage';

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
     * @return string[]
     */
    public function findIdsByUserAndDateRange(User $user, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        /** @var array<int, array{id: mixed}> $rows */
        $rows = $this->createQueryBuilder('w')
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
     * @param string[] $workoutIds
     * @return array{primary: list<string>, secondary: list<string>}
     */
    public function findSvgIdsByWorkoutIds(array $workoutIds): array
    {
        if ([] === $workoutIds) {
            return [
                'primary' => [],
                'secondary' => [],
            ];
        }

        /** @var array<int, array{svgIds: mixed, muscleType: mixed}> $rows */
        $rows = $this->muscleGroupJoinQueryBuilder()
            ->select('mg.svgIds as svgIds', 'em.type as muscleType')
            ->andWhere('w.id IN (:ids)')
            ->setParameter('ids', $workoutIds)
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        $primary = [];
        $secondary = [];

        foreach ($rows as $row) {
            $svgIds = $row['svgIds'];
            if (! is_array($svgIds)) {
                continue;
            }
            /** @var list<string> $svgIds */

            $type = $row['muscleType'];
            $typeValue = $this->resolveMuscleType($type);

            if (MuscleTypeEnum::PRIMARY->value === $typeValue) {
                $primary = array_merge($primary, $svgIds);
            } else {
                $secondary = array_merge($secondary, $svgIds);
            }
        }

        $primary = array_values(array_unique($primary));
        $secondary = array_values(array_diff(array_unique($secondary), $primary));

        return [
            'primary' => $primary,
            'secondary' => $secondary,
        ];
    }

    /**
     * Nombre de séries par groupe musculaire sollicité (primaire OU secondaire confondus),
     * sur un ensemble de workouts, avec le détail primaire/secondaire. Un groupe non sollicité
     * n'apparaît pas dans le résultat.
     *
     * @param string[] $workoutIds
     * @return array<int, array{id: string, name: string, sets: int, primarySets: int, secondarySets: int}>
     */
    public function findMuscleGroupSetCountsByWorkoutIds(array $workoutIds): array
    {
        if ([] === $workoutIds) {
            return [];
        }

        /** @var array<int, array{muscleGroupId: mixed, muscleGroupName: mixed, muscleType: mixed}> $rows */
        $rows = $this->muscleGroupJoinQueryBuilder()
            ->select('mg.id as muscleGroupId', 'mg.name as muscleGroupName', 'em.type as muscleType')
            ->join('we.exerciseSets', 'es')
            ->andWhere('w.id IN (:ids)')
            ->setParameter('ids', $workoutIds)
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        /** @var array<string, array{id: string, name: string, sets: int, primarySets: int, secondarySets: int}> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            /** @var \Stringable|string $muscleGroupId */
            $muscleGroupId = $row['muscleGroupId'];
            $id = (string) $muscleGroupId;

            /** @var string $muscleGroupName */
            $muscleGroupName = $row['muscleGroupName'];

            $type = $row['muscleType'];
            $typeValue = $this->resolveMuscleType($type);

            if (! isset($grouped[$id])) {
                $grouped[$id] = [
                    'id' => $id,
                    'name' => $muscleGroupName,
                    'sets' => 0,
                    'primarySets' => 0,
                    'secondarySets' => 0,
                ];
            }

            $grouped[$id]['sets']++;

            if (MuscleTypeEnum::PRIMARY->value === $typeValue) {
                $grouped[$id]['primarySets']++;
            } else {
                $grouped[$id]['secondarySets']++;
            }
        }

        return array_values($grouped);
    }

    /**
     * Date de la dernière séance ayant sollicité chaque groupe musculaire (primaire ou
     * secondaire), sur tout l'historique de l'utilisateur — une seule requête, le MAX par
     * groupe est calculé en PHP plutôt qu'en DQL (évite l'ambiguïté d'hydratation d'un
     * agrégat MAX() sur un champ datetime_immutable, même philosophie que le regroupement
     * par semaine/mois du widget Tonnage).
     *
     * @return array<string, DateTimeImmutable> groupe musculaire (id) => date de dernière sollicitation
     */
    public function findLastSolicitationDatesByMuscleGroup(User $user): array
    {
        /** @var array<int, array{muscleGroupId: mixed, performedAt: mixed}> $rows */
        $rows = $this->muscleGroupJoinQueryBuilder()
            ->select('mg.id as muscleGroupId', 'w.performedAt as performedAt')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        /** @var array<string, DateTimeImmutable> $result */
        $result = [];

        foreach ($rows as $row) {
            /** @var \Stringable|string $muscleGroupId */
            $muscleGroupId = $row['muscleGroupId'];
            $id = (string) $muscleGroupId;

            /** @var DateTimeImmutable $performedAt */
            $performedAt = $row['performedAt'];

            if (! isset($result[$id]) || $performedAt > $result[$id]) {
                $result[$id] = $performedAt;
            }
        }

        return $result;
    }

    /**
     * @return \DateTimeImmutable[]
     */
    public function findAllPerformedDatesByUser(User $user): array
    {
        /** @var array<int, array{performedAt: \DateTimeImmutable}> $rows */
        $rows = $this->createQueryBuilder('w')
            ->select('w.performedAt')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        return array_map(static fn (array $row): \DateTimeImmutable => $row['performedAt'], $rows);
    }

    public function countByUserAndDate(User $user, DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        return (int) $this->createQueryBuilder('w')
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
        $qb = $this->createQueryBuilder('w')
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
     * @param string[] $workoutIds
     * @return array<string, float>
     */
    public function findTonnageByWorkoutIds(array $workoutIds): array
    {
        if (empty($workoutIds)) {
            return [];
        }

        /** @var array<int, array{workoutId: mixed, tonnage: mixed}> $rows */
        $rows = $this->createQueryBuilder('w')
            ->select('w.id as workoutId')
            ->addSelect(self::TONNAGE_SUBQUERY_DQL)
            ->andWhere('w.id IN (:ids)')
            ->setParameter('ids', $workoutIds)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            /** @var string|\Stringable $workoutId */
            $workoutId = $row['workoutId'];
            /** @var numeric|null $tonnage */
            $tonnage = $row['tonnage'];

            $result[(string) $workoutId] = null !== $tonnage ? (float) $tonnage : 0.0;
        }

        return $result;
    }

    /**
     * Une ligne par séance (date + tonnage en kg) sur la plage donnée, triée chronologiquement.
     * Sert de base à toutes les granularités du graphique de tonnage (jour, semaine, mois),
     * le regroupement se fait ensuite en PHP.
     *
     * @return array<int, array{performedAt: DateTimeImmutable, tonnage: float}>
     */
    public function findTonnageSeriesByUser(User $user, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        /** @var array<int, array{performedAt: mixed, tonnage: mixed}> $rows */
        $rows = $this->createQueryBuilder('w')
            ->select('w.performedAt as performedAt')
            ->addSelect(self::TONNAGE_SUBQUERY_DQL)
            ->andWhere('w.owner = :user')
            ->andWhere('w.performedAt >= :start')
            ->andWhere('w.performedAt <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('w.performedAt', 'ASC')
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        $result = [];
        foreach ($rows as $row) {
            /** @var \DateTimeImmutable $performedAt */
            $performedAt = $row['performedAt'];
            /** @var numeric $tonnage */
            $tonnage = $row['tonnage'];

            $result[] = [
                'performedAt' => $performedAt,
                'tonnage' => (float) $tonnage,
            ];
        }

        return $result;
    }

    /**
     * @param string[] $workoutIds
     * @return array<string, array<int, array{name: string, type: string}>>
     */
    public function findMusclesByWorkoutIds(array $workoutIds): array
    {
        if (empty($workoutIds)) {
            return [];
        }

        $rows = $this->fetchMuscleRows($workoutIds);
        $grouped = $this->deduplicateMuscles($rows);

        return $this->sortMusclesByType($grouped);
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
        $rows = $this->getEntityManager()
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

    /**
     * DQL hydrate `em.type` tantôt en enum backed, tantôt en scalaire selon le contexte de
     * sélection — normalisation en un point unique plutôt que dupliquée à chaque appelant.
     */
    private function resolveMuscleType(mixed $type): string
    {
        if ($type instanceof \BackedEnum) {
            return (string) $type->value;
        }

        /** @var string $type */
        return $type;
    }

    /**
     * @param string[] $workoutIds
     * @return array<int, array{workoutId: mixed, muscleName: string, musclePosition: mixed, muscleType: mixed}>
     */
    private function fetchMuscleRows(array $workoutIds): array
    {
        /** @var array<int, array{workoutId: mixed, muscleName: string, musclePosition: mixed, muscleType: mixed}> $rows */
        $rows = $this->muscleGroupJoinQueryBuilder()
            ->select(
                'w.id as workoutId',
                'mg.name as muscleName',
                'mg.position as musclePosition',
                'em.type as muscleType'
            )
            ->andWhere('w.id IN (:ids)')
            ->setParameter('ids', $workoutIds)
            ->orderBy('mg.position', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Base commune (jointures exercice → muscles → groupe musculaire) réutilisée par toutes les
     * requêtes agrégeant par groupe musculaire — évite de répéter les 4 mêmes jointures dans
     * chaque méthode.
     */
    private function muscleGroupJoinQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('w')
            ->join('w.workoutExercises', 'we')
            ->join('we.exercise', 'e')
            ->join('e.exerciseMuscles', 'em')
            ->join('em.muscleGroup', 'mg');
    }

    /**
     * @param array<int, array{workoutId: mixed, muscleName: string, musclePosition: mixed, muscleType: mixed}> $rows
     * @return array<string, array<string, string>>
     */
    private function deduplicateMuscles(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            /** @var string|\Stringable $workoutId */
            $workoutId = $row['workoutId'];

            /** @var mixed $type */
            $type = $row['muscleType'];

            $wid = (string) $workoutId;
            $name = $row['muscleName'];
            $typeValue = $this->resolveMuscleType($type);

            if (! isset($grouped[$wid][$name])) {
                $grouped[$wid][$name] = $typeValue;
            } elseif ($typeValue === MuscleTypeEnum::PRIMARY->value) {
                $grouped[$wid][$name] = MuscleTypeEnum::PRIMARY->value;
            }
        }

        return $grouped;
    }

    /**
     * @param array<string, array<string, string>> $grouped
     * @return array<string, array<int, array{name: string, type: string}>>
     */
    private function sortMusclesByType(array $grouped): array
    {
        $result = [];

        foreach ($grouped as $wid => $muscles) {
            $primaries = [];
            $secondaries = [];

            foreach ($muscles as $name => $type) {
                if ($type === MuscleTypeEnum::PRIMARY->value) {
                    $primaries[] = [
                        'name' => $name,
                        'type' => MuscleTypeEnum::PRIMARY->value,
                    ];
                } else {
                    $secondaries[] = [
                        'name' => $name,
                        'type' => MuscleTypeEnum::SECONDARY->value,
                    ];
                }
            }

            $result[$wid] = array_merge($primaries, $secondaries);
        }

        return $result;
    }
}
