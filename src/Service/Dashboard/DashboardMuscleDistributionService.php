<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Repository\WorkoutRepository;

final readonly class DashboardMuscleDistributionService
{
    private const int MAX_BARS = 8;

    private const int PERCENTAGE_SCALE = 100;

    public function __construct(
        private WorkoutRepository $workoutRepository,
    ) {
    }

    /**
     * Répartition des séries par groupe musculaire sollicité sur un ensemble de workouts,
     * limitée aux 8 groupes les plus pertinents. Tri par nombre total de séries décroissant
     * (primaire + secondaire confondus), sans distinction de niveau d'implication — un tri brut
     * assumé plutôt qu'une pondération primaire/secondaire jugée arbitraire. Le pourcentage de
     * chaque barre reste relatif au groupe le plus sollicité de la période complète, pas
     * seulement du top 8.
     *
     * @param string[]                          $workoutIds
     * @param array<string, \DateTimeImmutable> $lastSolicitationDates groupe musculaire (id) => date de
     *                                                                  dernière sollicitation sur tout
     *                                                                  l'historique — vide si non pertinent
     *                                                                  (filtre "Séance")
     * @return array{bars: array<int, array{name: string, sets: int, percentage: int, primarySets: int, secondarySets: int, primaryPercentage: int, secondaryPercentage: int, daysSinceLastSolicited: int|null}>, remainingCount: int}
     */
    public function getBars(array $workoutIds, array $lastSolicitationDates = []): array
    {
        $counts = $this->workoutRepository->findMuscleGroupSetCountsByWorkoutIds($workoutIds);

        if ([] === $counts) {
            return [
                'bars' => [],
                'remainingCount' => 0,
            ];
        }

        $max = max(array_column($counts, 'sets'));
        $total = \count($counts);

        usort(
            $counts,
            static fn (array $a, array $b): int => $b['sets'] <=> $a['sets'],
        );
        $limited = \array_slice($counts, 0, self::MAX_BARS);

        $today = new \DateTimeImmutable('today');

        $bars = array_map(
            function (array $count) use ($max, $lastSolicitationDates, $today): array {
                $primaryPercentage = (int) round($count['primarySets'] / $count['sets'] * self::PERCENTAGE_SCALE);

                $lastSolicitedAt = $lastSolicitationDates[$count['id']] ?? null;

                return [
                    'name' => $count['name'],
                    'sets' => $count['sets'],
                    'percentage' => (int) round($count['sets'] / $max * self::PERCENTAGE_SCALE),
                    'primarySets' => $count['primarySets'],
                    'secondarySets' => $count['secondarySets'],
                    'primaryPercentage' => $primaryPercentage,
                    'secondaryPercentage' => self::PERCENTAGE_SCALE - $primaryPercentage,
                    'daysSinceLastSolicited' => null !== $lastSolicitedAt
                        ? $this->daysBetween($lastSolicitedAt, $today)
                        : null,
                ];
            },
            $limited,
        );

        return [
            'bars' => $bars,
            'remainingCount' => max(0, $total - self::MAX_BARS),
        ];
    }

    private function daysBetween(\DateTimeImmutable $from, \DateTimeImmutable $today): int
    {
        $diff = $today->diff($from->setTime(0, 0, 0));

        return (int) $diff->days;
    }
}
