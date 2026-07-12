<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Repository\ExerciseSetRepository;

readonly class DashboardPrService
{
    public function __construct(
        private ExerciseSetRepository $exerciseSetRepository,
    ) {
    }

    /**
     * Nombre de nouveaux records personnels (PR) battus par filtre du widget Séance (Dernière
     * séance/Semaine/Mois courant) — même définition de PR que WorkoutShowController (poids max
     * sur un exercice, jamais poids × reps), mais détectée progressivement sur tout l'historique
     * chronologique de l'utilisateur pour compter chaque record individuellement battu pendant
     * la période (une progression de 2 PR sur le même exercice pendant la période compte pour 2,
     * pas 1) — comparaison strictement supérieure au record précédent, une égalité de poids
     * n'est pas un nouveau record "battu".
     *
     * @return array{last: int, week: int, month: int}
     */
    public function countPrsByFilter(
        User $user,
        string $lastWorkoutId,
        DashboardPeriod $week,
        DashboardPeriod $month,
    ): array {
        $sets = $this->exerciseSetRepository->findAllSetsChronologicallyByUser($user);
        $prEvents = $this->detectNewPrEvents($sets);

        $last = 0;
        $weekCount = 0;
        $monthCount = 0;

        foreach ($prEvents as $event) {
            if ($event['workoutId'] === $lastWorkoutId) {
                $last++;
            }

            if ($event['performedAt'] >= $week->start && $event['performedAt'] <= $week->end) {
                $weekCount++;
            }

            if ($event['performedAt'] >= $month->start && $event['performedAt'] <= $month->end) {
                $monthCount++;
            }
        }

        return [
            'last' => $last,
            'week' => $weekCount,
            'month' => $monthCount,
        ];
    }

    /**
     * @param array<int, array{workoutId: string, exerciseId: string, weight: float, performedAt: \DateTimeImmutable}> $sets
     * @return array<int, array{workoutId: string, performedAt: \DateTimeImmutable}>
     */
    private function detectNewPrEvents(array $sets): array
    {
        /** @var array<string, float> $runningMaxByExercise */
        $runningMaxByExercise = [];
        $events = [];

        foreach ($sets as $set) {
            $currentMax = $runningMaxByExercise[$set['exerciseId']] ?? null;

            if (null === $currentMax || $set['weight'] > $currentMax) {
                $runningMaxByExercise[$set['exerciseId']] = $set['weight'];
                $events[] = [
                    'workoutId' => $set['workoutId'],
                    'performedAt' => $set['performedAt'],
                ];
            }
        }

        return $events;
    }
}
