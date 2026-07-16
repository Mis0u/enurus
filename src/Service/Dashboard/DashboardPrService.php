<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Repository\ExerciseSetRepository;

final readonly class DashboardPrService
{
    public function __construct(
        private ExerciseSetRepository $exerciseSetRepository,
    ) {
    }

    /**
     * Nombre de nouveaux records personnels (PR) de poids battus par filtre du widget Séance
     * (Dernière séance/Semaine/Mois courant) — même définition de PR que WorkoutShowController
     * (poids max sur un exercice, jamais poids × reps, un seul PR possible par séance et par
     * exercice), détectée progressivement sur tout l'historique chronologique (2 séances distinctes
     * battant chacune le record sur le même exercice pendant la période comptent pour 2, pas 1).
     * Un exercice jamais fait avant compte automatiquement comme un premier record.
     *
     * @return array{last: int, week: int, month: int}
     */
    public function countPrsByFilter(
        User $user,
        string $lastWorkoutId,
        DashboardPeriod $week,
        DashboardPeriod $month,
    ): array {
        $rows = $this->exerciseSetRepository->findMaxWeightPerWorkoutAndExerciseChronologicallyByUser($user);

        $entries = array_map(
            static fn (array $row): array => [
                'key' => $row['exerciseId'],
                'value' => $row['weight'],
                'workoutId' => $row['workoutId'],
                'performedAt' => $row['performedAt'],
            ],
            $rows,
        );

        $events = $this->detectNewRecordEvents($entries, firstAttemptCounts: true);

        return $this->countEventsByFilter($events, $lastWorkoutId, $week, $month);
    }

    /**
     * Nombre de nouveaux records de répétitions (même poids qu'avant, mais jamais fait à autant de
     * reps) battus par filtre — même mécanique de détection progressive que `countPrsByFilter`,
     * mais la clé de comparaison est (exercice, poids exact) au lieu de (exercice) seul, et un
     * poids jamais fait avant ne compte volontairement pas comme un record de reps automatique
     * (ce cas est déjà couvert par le PR de poids : pas de double comptage).
     *
     * @return array{last: int, week: int, month: int}
     */
    public function countRepsRecordsByFilter(
        User $user,
        string $lastWorkoutId,
        DashboardPeriod $week,
        DashboardPeriod $month,
    ): array {
        $rows = $this->exerciseSetRepository->findMaxRepsPerWorkoutExerciseAndWeightChronologicallyByUser($user);

        $entries = array_map(
            static fn (array $row): array => [
                'key' => $row['exerciseId'] . '|' . ExerciseSetRepository::weightKey($row['weight']),
                'value' => (float) $row['reps'],
                'workoutId' => $row['workoutId'],
                'performedAt' => $row['performedAt'],
            ],
            $rows,
        );

        $events = $this->detectNewRecordEvents($entries, firstAttemptCounts: false);

        return $this->countEventsByFilter($events, $lastWorkoutId, $week, $month);
    }

    /**
     * Détecte, en parcourant des entrées déjà triées chronologiquement, chaque fois que la valeur
     * dépasse strictement le record précédent pour sa clé — une égalité n'est jamais un nouveau
     * record. `$firstAttemptCounts` décide si l'absence totale d'historique pour une clé compte
     * comme un record immédiat (poids : oui, premier essai = record) ou non (reps à un poids donné :
     * non, sans quoi tout nouveau poids max déclencherait aussi un "record de reps" trivial et
     * redondant avec le PR de poids).
     *
     * @param array<int, array{key: string, value: float, workoutId: string, performedAt: \DateTimeImmutable}> $entries
     * @return array<int, array{workoutId: string, performedAt: \DateTimeImmutable}>
     */
    private function detectNewRecordEvents(array $entries, bool $firstAttemptCounts): array
    {
        /** @var array<string, float> $runningMaxByKey */
        $runningMaxByKey = [];
        $events = [];

        foreach ($entries as $entry) {
            $currentMax = $runningMaxByKey[$entry['key']] ?? null;
            $isNewRecord = null === $currentMax ? $firstAttemptCounts : $entry['value'] > $currentMax;

            if (null === $currentMax || $entry['value'] > $currentMax) {
                $runningMaxByKey[$entry['key']] = $entry['value'];
            }

            if ($isNewRecord) {
                $events[] = [
                    'workoutId' => $entry['workoutId'],
                    'performedAt' => $entry['performedAt'],
                ];
            }
        }

        return $events;
    }

    /**
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable}> $events
     * @return array{last: int, week: int, month: int}
     */
    private function countEventsByFilter(
        array $events,
        string $lastWorkoutId,
        DashboardPeriod $week,
        DashboardPeriod $month,
    ): array {
        $last = 0;
        $weekCount = 0;
        $monthCount = 0;

        foreach ($events as $event) {
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
}
