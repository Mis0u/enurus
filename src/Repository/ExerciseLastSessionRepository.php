<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\User;
use App\Entity\WorkoutExercise;
use App\Service\Workout\LastExerciseSession;
use DateTimeImmutable;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Séries de la dernière séance d'un utilisateur par exercice — sert à pré-remplir une carte
 * d'exercice avec la performance précédente. Service à part plutôt qu'une méthode de plus dans
 * `ExerciseSetRepository` (déjà très chargé), même découpage que `WorkoutStatsRepository`.
 */
class ExerciseLastSessionRepository
{
    public function __construct(
        private readonly ExerciseSetRepository $exerciseSetRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Une seule requête pour tous les exercices (sous-requête corrélée sur la date de séance la
     * plus récente par exercice, jamais un `setMaxResults()` sur des lignes jointes). Deux séances
     * à la même date exacte : la plus récemment créée l'emporte (`w.id` UUIDv7, tri décroissant).
     *
     * @param Exercise[] $exercises
     * @return array<string, LastExerciseSession> clé = identifiant de l'exercice ; absente si
     *                                            l'exercice n'a jamais été pratiqué
     */
    public function findLastSessionByExercise(User $user, array $exercises): array
    {
        if ([] === $exercises) {
            return [];
        }

        $lastSessions = [];
        $keptWorkoutIds = [];
        foreach ($this->findLastSessionRows($user, $exercises) as $row) {
            $exerciseId = (string) $row['exerciseId'];
            $workoutId = (string) $row['workoutId'];
            $keptWorkoutIds[$exerciseId] ??= $workoutId;

            if ($keptWorkoutIds[$exerciseId] !== $workoutId) {
                continue;
            }

            $lastSessions[$exerciseId]['performedAt'] = $row['performedAt'];
            $lastSessions[$exerciseId]['sets'][] = [
                'weight' => (float) $row['weight'],
                'reps' => (int) $row['reps'],
                'duration' => null !== $row['duration'] ? (int) $row['duration'] : null,
                'distance' => null !== $row['distance'] ? (int) $row['distance'] : null,
            ];
        }

        return array_map(
            static fn (array $session): LastExerciseSession => new LastExerciseSession($session['performedAt'], $session['sets']),
            $lastSessions,
        );
    }

    /**
     * @param Exercise[] $exercises
     * @return list<array{exerciseId: \Stringable|string, workoutId: \Stringable|string, performedAt: DateTimeImmutable, weight: numeric, reps: numeric, duration: numeric|null, distance: numeric|null}>
     */
    private function findLastSessionRows(User $user, array $exercises): array
    {
        $latestPerformedAtDql = $this->entityManager->createQueryBuilder()
            ->select('MAX(latestWorkout.performedAt)')
            ->from(WorkoutExercise::class, 'latestWorkoutExercise')
            ->join('latestWorkoutExercise.workout', 'latestWorkout')
            ->andWhere('latestWorkout.owner = :user')
            ->andWhere('latestWorkoutExercise.exercise = we.exercise')
            ->getDQL();

        /** @var list<array{exerciseId: \Stringable|string, workoutId: \Stringable|string, performedAt: DateTimeImmutable, weight: numeric, reps: numeric, duration: numeric|null, distance: numeric|null}> $rows */
        $rows = $this->exerciseSetRepository->createQueryBuilder('es')
            ->select('IDENTITY(we.exercise) AS exerciseId', 'w.id AS workoutId', 'w.performedAt AS performedAt', 'es.weight AS weight', 'es.reps AS reps', 'es.duration AS duration', 'es.distance AS distance')
            ->join('es.workoutExercise', 'we')
            ->join('we.workout', 'w')
            ->andWhere('w.owner = :user')
            ->andWhere('we.exercise IN (:exercises)')
            ->andWhere(\sprintf('w.performedAt = (%s)', $latestPerformedAtDql))
            ->setParameter('user', $user)
            ->setParameter('exercises', $exercises)
            ->orderBy('w.id', 'DESC')
            ->addOrderBy('we.position', 'ASC')
            ->addOrderBy('es.position', 'ASC')
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        return $rows;
    }
}
