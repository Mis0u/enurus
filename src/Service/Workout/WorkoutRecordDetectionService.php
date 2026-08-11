<?php

declare(strict_types=1);

namespace App\Service\Workout;

use App\Entity\User;
use App\Repository\ExerciseSetRepository;

/**
 * Détection des PR (records de poids) et records de reps sur l'historique d'un user — logique
 * partagée entre le dashboard (DashboardPrService, comptage par filtre) et la liste des séances
 * (WorkoutListController, présence par séance), pour éviter de dupliquer la règle métier
 * "une valeur ne devient un nouveau record que si elle dépasse strictement le max précédent pour
 * sa clé" dans plusieurs services.
 */
readonly class WorkoutRecordDetectionService
{
    public function __construct(
        private ExerciseSetRepository $exerciseSetRepository,
    ) {
    }

    /**
     * PR de poids (exercices `WEIGHT_REPS`) et PR de durée (exercices `TIME`, ex. gainage) fusionnés
     * dans le même flux d'événements : un exercice appartient exclusivement à l'un ou l'autre type
     * de mesure, les clés (`exerciseId`) ne se chevauchent donc jamais entre les deux sous-listes —
     * une simple concaténation suffit, chaque sous-liste restant déjà triée chronologiquement.
     *
     * @return array<int, array{workoutId: string, performedAt: \DateTimeImmutable}>
     */
    public function findPrEvents(User $user): array
    {
        $weightRows = $this->exerciseSetRepository->findMaxWeightPerWorkoutAndExerciseChronologicallyByUser($user);
        $durationRows = $this->exerciseSetRepository->findMaxDurationPerWorkoutAndExerciseChronologicallyByUser($user);

        $entries = [
            ...array_map(
                static fn (array $row): array => [
                    'key' => $row['exerciseId'],
                    'value' => $row['weight'],
                    'workoutId' => $row['workoutId'],
                    'performedAt' => $row['performedAt'],
                ],
                $weightRows,
            ),
            ...array_map(
                static fn (array $row): array => [
                    'key' => $row['exerciseId'],
                    'value' => (float) $row['duration'],
                    'workoutId' => $row['workoutId'],
                    'performedAt' => $row['performedAt'],
                ],
                $durationRows,
            ),
        ];

        return $this->detectNewRecordEvents($entries, firstAttemptCounts: true);
    }

    /**
     * @return array<int, array{workoutId: string, performedAt: \DateTimeImmutable}>
     */
    public function findRepsRecordEvents(User $user): array
    {
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

        return $this->detectNewRecordEvents($entries, firstAttemptCounts: false);
    }

    /**
     * Pour chaque id de `$workoutIds`, indique s'il contient au moins un PR — peu importe le
     * nombre de PR dans la séance, un seul événement suffit à passer le flag à `true`.
     *
     * @param array<string> $workoutIds
     * @return array<string, bool>
     */
    public function hasPrByWorkoutId(User $user, array $workoutIds): array
    {
        return $this->buildPresenceMap($this->findPrEvents($user), $workoutIds);
    }

    /**
     * @param array<string> $workoutIds
     * @return array<string, bool>
     */
    public function hasRepsRecordByWorkoutId(User $user, array $workoutIds): array
    {
        return $this->buildPresenceMap($this->findRepsRecordEvents($user), $workoutIds);
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
     * @param array<string> $workoutIds
     * @return array<string, bool>
     */
    private function buildPresenceMap(array $events, array $workoutIds): array
    {
        $map = array_fill_keys($workoutIds, false);

        foreach ($events as $event) {
            if (\array_key_exists($event['workoutId'], $map)) {
                $map[$event['workoutId']] = true;
            }
        }

        return $map;
    }
}
