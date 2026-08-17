<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Enum\Entity\Exercise\MeasurementType;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExerciseSet>
 */
class ExerciseSetRepository extends ServiceEntityRepository
{
    /**
     * Poids réellement soulevé pour une série — `es.weight` seul pour un exercice classique, ou
     * lest + part de poids de corps figée pour un exercice bodyweight (`e.bodyweightPercent` non
     * null) — voir le docblock de `ExerciseSet::weight`. Aliases `es`/`e` fixés par
     * `queryForUser()`, réutilisés par toutes les requêtes de ce repository.
     */
    private const string EFFECTIVE_WEIGHT_DQL = '(CASE WHEN e.bodyweightPercent IS NOT NULL
        THEN (COALESCE(es.bodyweightSnapshotKg, 0)) * e.bodyweightPercent / 100 + es.weight
        ELSE es.weight
        END)';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciseSet::class);
    }

    /**
     * Détermine si un exercice a déjà au moins une série enregistrée — sert à verrouiller
     * `Exercise::measurementType` une fois l'exercice utilisé (cf. ExerciseType), pour ne jamais
     * mélanger des séries reps, durée et distance sur le même exercice.
     */
    public function existsForExercise(Exercise $exercise): bool
    {
        return null !== $this->createQueryBuilder('es')
            ->select('es.id')
            ->join('es.workoutExercise', 'we')
            ->andWhere('we.exercise = :exercise')
            ->setParameter('exercise', $exercise)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Poids max par exercice sur les séances strictement antérieures à `$before` — sert de
     * référence pour déterminer si une séance donnée a battu le record précédent. Exclut
     * volontairement la séance affichée elle-même : sinon son propre poids serait déjà compté
     * dans le max, et une simple égalité (poids identique, jamais dépassé) afficherait à tort le
     * badge PR — même règle stricte que `DashboardPrService` ("une égalité de poids n'est pas un
     * nouveau record battu").
     *
     * @param array<Exercise> $exercises
     * @return array<string, float>
     */
    public function findMaxWeightPerExerciseBeforeDate(User $user, array $exercises, DateTimeImmutable $before): array
    {
        if (empty($exercises)) {
            return [];
        }

        /** @var array<int, array{exerciseId: mixed, maxWeight: mixed}> $results */
        $results = $this->queryForUser($user)
            ->select('e.id AS exerciseId, MAX(' . self::EFFECTIVE_WEIGHT_DQL . ') AS maxWeight')
            ->andWhere('e IN (:exercises)')
            ->andWhere('e.measurementType = :weightRepsType')
            ->andWhere('w.performedAt < :before')
            ->setParameter('exercises', $exercises)
            ->setParameter('weightRepsType', MeasurementType::WEIGHT_REPS)
            ->setParameter('before', $before)
            ->groupBy('e.id')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($results as $row) {
            /** @var string|\Stringable $exerciseId */
            $exerciseId = $row['exerciseId'];
            /** @var numeric|null $maxWeight */
            $maxWeight = $row['maxWeight'];
            $map[(string) $exerciseId] = null !== $maxWeight ? (float) $maxWeight : 0.0;
        }

        return $map;
    }

    /**
     * Pendant de `findMaxWeightPerExerciseBeforeDate` pour les exercices `TIME` : durée max (en
     * secondes) au lieu du poids max — sert de référence au badge PR sur la page de détail d'une
     * séance pour ces exercices.
     *
     * @param array<Exercise> $exercises
     * @return array<string, int>
     */
    public function findMaxDurationPerExerciseBeforeDate(User $user, array $exercises, DateTimeImmutable $before): array
    {
        if (empty($exercises)) {
            return [];
        }

        /** @var array<int, array{exerciseId: mixed, maxDuration: mixed}> $results */
        $results = $this->queryForUser($user)
            ->select('e.id AS exerciseId, MAX(es.duration) AS maxDuration')
            ->andWhere('e IN (:exercises)')
            ->andWhere('e.measurementType = :timeType')
            ->andWhere('w.performedAt < :before')
            ->setParameter('exercises', $exercises)
            ->setParameter('timeType', MeasurementType::TIME)
            ->setParameter('before', $before)
            ->groupBy('e.id')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($results as $row) {
            /** @var string|\Stringable $exerciseId */
            $exerciseId = $row['exerciseId'];
            /** @var numeric|null $maxDuration */
            $maxDuration = $row['maxDuration'];
            $map[(string) $exerciseId] = null !== $maxDuration ? (int) $maxDuration : 0;
        }

        return $map;
    }

    /**
     * Pendant de `findMaxWeightPerExerciseBeforeDate` pour les exercices `DISTANCE` : distance max
     * (en mètres) au lieu du poids max — sert de référence au badge PR sur la page de détail d'une
     * séance pour ces exercices.
     *
     * @param array<Exercise> $exercises
     * @return array<string, int>
     */
    public function findMaxDistancePerExerciseBeforeDate(User $user, array $exercises, DateTimeImmutable $before): array
    {
        if (empty($exercises)) {
            return [];
        }

        /** @var array<int, array{exerciseId: mixed, maxDistance: mixed}> $results */
        $results = $this->queryForUser($user)
            ->select('e.id AS exerciseId, MAX(es.distance) AS maxDistance')
            ->andWhere('e IN (:exercises)')
            ->andWhere('e.measurementType = :distanceType')
            ->andWhere('w.performedAt < :before')
            ->setParameter('exercises', $exercises)
            ->setParameter('distanceType', MeasurementType::DISTANCE)
            ->setParameter('before', $before)
            ->groupBy('e.id')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($results as $row) {
            /** @var string|\Stringable $exerciseId */
            $exerciseId = $row['exerciseId'];
            /** @var numeric|null $maxDistance */
            $maxDistance = $row['maxDistance'];
            $map[(string) $exerciseId] = null !== $maxDistance ? (int) $maxDistance : 0;
        }

        return $map;
    }

    /**
     * Reps max par (exercice, poids exact) sur les séances strictement antérieures à `$before` —
     * même rôle que `findMaxWeightPerExerciseBeforeDate`, mais pour détecter un "record de reps"
     * (même poids qu'avant, mais jamais fait à autant de répétitions). Clé composite exercice+
     * poids indispensable : les reps ne se comparent qu'à poids égal, jamais entre poids
     * différents.
     *
     * @param array<Exercise> $exercises
     * @return array<string, array<string, int>> exerciseId => (poids en chaîne) => reps max
     */
    public function findMaxRepsPerWeightBeforeDate(User $user, array $exercises, DateTimeImmutable $before): array
    {
        if (empty($exercises)) {
            return [];
        }

        /** @var array<int, array{exerciseId: mixed, weight: mixed, maxReps: mixed}> $results */
        $results = $this->queryForUser($user)
            ->select('e.id AS exerciseId, ' . self::EFFECTIVE_WEIGHT_DQL . ' AS weight, MAX(es.reps) AS maxReps')
            ->andWhere('e IN (:exercises)')
            ->andWhere('e.measurementType = :weightRepsType')
            ->andWhere('w.performedAt < :before')
            ->setParameter('exercises', $exercises)
            ->setParameter('weightRepsType', MeasurementType::WEIGHT_REPS)
            ->setParameter('before', $before)
            ->groupBy('e.id')
            ->addGroupBy('weight')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($results as $row) {
            /** @var string|\Stringable $exerciseId */
            $exerciseId = $row['exerciseId'];
            /** @var numeric $weight */
            $weight = $row['weight'];
            /** @var numeric $maxReps */
            $maxReps = $row['maxReps'];

            $map[(string) $exerciseId][self::weightKey((float) $weight)] = (int) $maxReps;
        }

        return $map;
    }

    /**
     * Poids max par (séance, exercice), triés chronologiquement par séance — sert de base à la
     * détection progressive des records personnels. Agrégation par séance ET exercice
     * indispensable : sans elle, une séance avec plusieurs sets progressifs sur le même exercice
     * (ex. échauffement 100kg → 110kg → poids de travail 130kg) compterait un PR par set qui
     * progresse, au lieu d'un seul PR pour la séance sur cet exercice — même définition de PR que
     * WorkoutShowController (le meilleur set de la séance sur l'exercice, jamais un set
     * intermédiaire).
     *
     * @return array<int, array{workoutId: string, exerciseId: string, weight: float, performedAt: DateTimeImmutable}>
     */
    public function findMaxWeightPerWorkoutAndExerciseChronologicallyByUser(User $user): array
    {
        /** @var array<int, array{workoutId: mixed, exerciseId: mixed, weight: mixed, performedAt: mixed}> $rows */
        $rows = $this->queryForUser($user)
            ->select('w.id as workoutId', 'e.id as exerciseId', 'MAX(' . self::EFFECTIVE_WEIGHT_DQL . ') as weight', 'w.performedAt as performedAt')
            ->andWhere('e.measurementType = :weightRepsType')
            ->setParameter('weightRepsType', MeasurementType::WEIGHT_REPS)
            ->groupBy('w.id')
            ->addGroupBy('e.id')
            ->addGroupBy('w.performedAt')
            ->orderBy('w.performedAt', 'ASC')
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        $result = [];
        foreach ($rows as $row) {
            /** @var \Stringable|string $workoutId */
            $workoutId = $row['workoutId'];
            /** @var \Stringable|string $exerciseId */
            $exerciseId = $row['exerciseId'];
            /** @var numeric $weight */
            $weight = $row['weight'];
            /** @var DateTimeImmutable $performedAt */
            $performedAt = $row['performedAt'];

            $result[] = [
                'workoutId' => (string) $workoutId,
                'exerciseId' => (string) $exerciseId,
                'weight' => (float) $weight,
                'performedAt' => $performedAt,
            ];
        }

        return $result;
    }

    /**
     * Pendant de `findMaxWeightPerWorkoutAndExerciseChronologicallyByUser` pour les exercices
     * `TIME` : durée max (en secondes) au lieu du poids max — même définition de PR (le meilleur
     * set de la séance sur l'exercice), le poids éventuellement ajouté est ignoré du calcul.
     *
     * @return array<int, array{workoutId: string, exerciseId: string, duration: int, performedAt: DateTimeImmutable}>
     */
    public function findMaxDurationPerWorkoutAndExerciseChronologicallyByUser(User $user): array
    {
        /** @var array<int, array{workoutId: mixed, exerciseId: mixed, duration: mixed, performedAt: mixed}> $rows */
        $rows = $this->queryForUser($user)
            ->select('w.id as workoutId', 'e.id as exerciseId', 'MAX(es.duration) as duration', 'w.performedAt as performedAt')
            ->andWhere('e.measurementType = :timeType')
            ->setParameter('timeType', MeasurementType::TIME)
            ->groupBy('w.id')
            ->addGroupBy('e.id')
            ->addGroupBy('w.performedAt')
            ->orderBy('w.performedAt', 'ASC')
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        $result = [];
        foreach ($rows as $row) {
            /** @var \Stringable|string $workoutId */
            $workoutId = $row['workoutId'];
            /** @var \Stringable|string $exerciseId */
            $exerciseId = $row['exerciseId'];
            /** @var numeric $duration */
            $duration = $row['duration'];
            /** @var DateTimeImmutable $performedAt */
            $performedAt = $row['performedAt'];

            $result[] = [
                'workoutId' => (string) $workoutId,
                'exerciseId' => (string) $exerciseId,
                'duration' => (int) $duration,
                'performedAt' => $performedAt,
            ];
        }

        return $result;
    }

    /**
     * Pendant de `findMaxWeightPerWorkoutAndExerciseChronologicallyByUser` pour les exercices
     * `DISTANCE` : distance max (en mètres) au lieu du poids max — même définition de PR (le
     * meilleur set de la séance sur l'exercice), le poids éventuellement ajouté est ignoré du
     * calcul.
     *
     * @return array<int, array{workoutId: string, exerciseId: string, distance: int, performedAt: DateTimeImmutable}>
     */
    public function findMaxDistancePerWorkoutAndExerciseChronologicallyByUser(User $user): array
    {
        /** @var array<int, array{workoutId: mixed, exerciseId: mixed, distance: mixed, performedAt: mixed}> $rows */
        $rows = $this->queryForUser($user)
            ->select('w.id as workoutId', 'e.id as exerciseId', 'MAX(es.distance) as distance', 'w.performedAt as performedAt')
            ->andWhere('e.measurementType = :distanceType')
            ->setParameter('distanceType', MeasurementType::DISTANCE)
            ->groupBy('w.id')
            ->addGroupBy('e.id')
            ->addGroupBy('w.performedAt')
            ->orderBy('w.performedAt', 'ASC')
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        $result = [];
        foreach ($rows as $row) {
            /** @var \Stringable|string $workoutId */
            $workoutId = $row['workoutId'];
            /** @var \Stringable|string $exerciseId */
            $exerciseId = $row['exerciseId'];
            /** @var numeric $distance */
            $distance = $row['distance'];
            /** @var DateTimeImmutable $performedAt */
            $performedAt = $row['performedAt'];

            $result[] = [
                'workoutId' => (string) $workoutId,
                'exerciseId' => (string) $exerciseId,
                'distance' => (int) $distance,
                'performedAt' => $performedAt,
            ];
        }

        return $result;
    }

    /**
     * Reps max par (séance, exercice, poids exact), triées chronologiquement — même principe que
     * `findMaxWeightPerWorkoutAndExerciseChronologicallyByUser`, mais pour détecter les records de
     * répétitions à poids égal.
     *
     * @return array<int, array{workoutId: string, exerciseId: string, weight: float, reps: int, performedAt: DateTimeImmutable}>
     */
    public function findMaxRepsPerWorkoutExerciseAndWeightChronologicallyByUser(User $user): array
    {
        /** @var array<int, array{workoutId: mixed, exerciseId: mixed, weight: mixed, reps: mixed, performedAt: mixed}> $rows */
        $rows = $this->queryForUser($user)
            ->select('w.id as workoutId', 'e.id as exerciseId', self::EFFECTIVE_WEIGHT_DQL . ' as weight', 'MAX(es.reps) as reps', 'w.performedAt as performedAt')
            ->andWhere('e.measurementType = :weightRepsType')
            ->setParameter('weightRepsType', MeasurementType::WEIGHT_REPS)
            ->groupBy('w.id')
            ->addGroupBy('e.id')
            ->addGroupBy('weight')
            ->addGroupBy('w.performedAt')
            ->orderBy('w.performedAt', 'ASC')
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        $result = [];
        foreach ($rows as $row) {
            /** @var \Stringable|string $workoutId */
            $workoutId = $row['workoutId'];
            /** @var \Stringable|string $exerciseId */
            $exerciseId = $row['exerciseId'];
            /** @var numeric $weight */
            $weight = $row['weight'];
            /** @var numeric $reps */
            $reps = $row['reps'];
            /** @var DateTimeImmutable $performedAt */
            $performedAt = $row['performedAt'];

            $result[] = [
                'workoutId' => (string) $workoutId,
                'exerciseId' => (string) $exerciseId,
                'weight' => (float) $weight,
                'reps' => (int) $reps,
                'performedAt' => $performedAt,
            ];
        }

        return $result;
    }

    /**
     * Sets bruts d'un exercice pour un user, triés chronologiquement par séance — sert de base à
     * la page d'historique par exercice (agrégation par séance faite en PHP, pas en DQL : la
     * "série max" d'une séance doit rester associée à ses propres reps, ce qu'un simple
     * `MAX(es.weight)` + `SUM(es.reps)` agrégés perdrait si la séance contient plusieurs séries à
     * poids différents — dataset borné à un seul exercice/user, le tri en PHP reste trivial).
     *
     * `weight` remonte déjà le poids effectif (lest + part de poids de corps figée) pour un
     * exercice bodyweight — voir `EFFECTIVE_WEIGHT_DQL` — les appelants n'ont donc jamais besoin de
     * connaître `bodyweightSnapshotKg`/`bodyweightPercent` séparément.
     *
     * @return array<int, array{workoutId: string, performedAt: DateTimeImmutable, weight: float, reps: int, duration: ?int, distance: ?int}>
     */
    public function findSessionHistoryForExerciseAndUser(User $user, Exercise $exercise): array
    {
        /** @var array<int, array{workoutId: mixed, performedAt: mixed, weight: mixed, reps: mixed, duration: mixed, distance: mixed}> $rows */
        $rows = $this->queryForUser($user)
            ->select('w.id as workoutId', 'w.performedAt as performedAt', self::EFFECTIVE_WEIGHT_DQL . ' as weight', 'es.reps as reps', 'es.duration as duration', 'es.distance as distance')
            ->andWhere('e = :exercise')
            ->setParameter('exercise', $exercise)
            ->orderBy('w.performedAt', 'ASC')
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        $result = [];
        foreach ($rows as $row) {
            /** @var \Stringable|string $workoutId */
            $workoutId = $row['workoutId'];
            /** @var DateTimeImmutable $performedAt */
            $performedAt = $row['performedAt'];
            /** @var numeric $weight */
            $weight = $row['weight'];
            /** @var numeric $reps */
            $reps = $row['reps'];
            /** @var numeric|null $duration */
            $duration = $row['duration'];
            /** @var numeric|null $distance */
            $distance = $row['distance'];

            $result[] = [
                'workoutId' => (string) $workoutId,
                'performedAt' => $performedAt,
                'weight' => (float) $weight,
                'reps' => (int) $reps,
                'duration' => null !== $duration ? (int) $duration : null,
                'distance' => null !== $distance ? (int) $distance : null,
            ];
        }

        return $result;
    }

    /**
     * Clé de tableau stable pour un poids exact — jamais un cast `(string)` brut : un poids rond
     * (ex. 130.0) donnerait la chaîne "130", que PHP convertit silencieusement en clé entière,
     * cassant le typage `array<string, ...>` attendu par les appelants (WorkoutShowController).
     */
    public static function weightKey(float $weight): string
    {
        return number_format($weight, 2, '.', '');
    }

    /**
     * Base commune (jointures + propriétaire) réutilisée par toutes les requêtes de détection de
     * records de ce repository — évite de répéter les mêmes 3 jointures dans chaque méthode.
     */
    private function queryForUser(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('es')
            ->join('es.workoutExercise', 'we')
            ->join('we.exercise', 'e')
            ->join('we.workout', 'w')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user);
    }
}
