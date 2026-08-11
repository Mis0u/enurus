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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciseSet::class);
    }

    /**
     * Détermine si un exercice a déjà au moins une série enregistrée — sert à verrouiller
     * `Exercise::measurementType` une fois l'exercice utilisé (cf. ExerciseType), pour ne jamais
     * mélanger des séries reps et des séries durée sur le même exercice.
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
            ->select('e.id AS exerciseId, MAX(es.weight) AS maxWeight')
            ->andWhere('e IN (:exercises)')
            ->andWhere('e.measurementType != :timeType')
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
            ->select('e.id AS exerciseId, es.weight AS weight, MAX(es.reps) AS maxReps')
            ->andWhere('e IN (:exercises)')
            ->andWhere('e.measurementType != :timeType')
            ->andWhere('w.performedAt < :before')
            ->setParameter('exercises', $exercises)
            ->setParameter('timeType', MeasurementType::TIME)
            ->setParameter('before', $before)
            ->groupBy('e.id')
            ->addGroupBy('es.weight')
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
            ->select('w.id as workoutId', 'e.id as exerciseId', 'MAX(es.weight) as weight', 'w.performedAt as performedAt')
            ->andWhere('e.measurementType != :timeType')
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
            ->select('w.id as workoutId', 'e.id as exerciseId', 'es.weight as weight', 'MAX(es.reps) as reps', 'w.performedAt as performedAt')
            ->andWhere('e.measurementType != :timeType')
            ->setParameter('timeType', MeasurementType::TIME)
            ->groupBy('w.id')
            ->addGroupBy('e.id')
            ->addGroupBy('es.weight')
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
