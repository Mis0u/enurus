<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Comparison;

use App\Service\Dashboard\DashboardPeriod;

/**
 * Tout ce que le widget Comparaison charge une seule fois, sur la plage qui couvre ses six périodes
 * (3 onglets × en cours / précédente) : une ligne par séance, le flux des records et les jours de
 * deload. Chaque période est ensuite additionnée en mémoire — 2 requêtes au lieu de 3 par période.
 */
final readonly class ComparisonSources
{
    /**
     * @param list<array{performedAt: \DateTimeImmutable, sets: int, reps: int}>     $workoutTotals cf. `WorkoutStatsRepository::findSetAndRepTotalsPerWorkout()`
     * @param array<int, array{performedAt: \DateTimeImmutable, tonnage: float}>     $tonnageRows   cf. `WorkoutTonnageRepository::findTonnageSeriesByUser()`, en kg
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable}> $prEvents      cf. `WorkoutRecordDetectionService::findPrEvents()`
     * @param array<string, true>                                                    $deloadDaySet  cf. `DeloadPeriodSetService::dayKeySet()`
     */
    public function __construct(
        private array $workoutTotals,
        private array $tonnageRows,
        private array $prEvents,
        private array $deloadDaySet,
    ) {
    }

    public function sessionCountIn(DashboardPeriod $period): int
    {
        return \count(self::within($this->workoutTotals, $period));
    }

    public function setCountIn(DashboardPeriod $period): int
    {
        return array_sum(array_column(self::within($this->workoutTotals, $period), 'sets'));
    }

    public function repCountIn(DashboardPeriod $period): int
    {
        return array_sum(array_column(self::within($this->workoutTotals, $period), 'reps'));
    }

    public function tonnageKgIn(DashboardPeriod $period): float
    {
        return (float) array_sum(array_column(self::within($this->tonnageRows, $period), 'tonnage'));
    }

    public function prCountIn(DashboardPeriod $period): int
    {
        return \count(self::within($this->prEvents, $period));
    }

    public function includesDeload(DashboardPeriod $period): bool
    {
        for ($day = $period->start->setTime(0, 0, 0); $day <= $period->end; $day = $day->modify('+1 day')) {
            if (isset($this->deloadDaySet[$day->format('Y-m-d')])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template TRow of array{performedAt: \DateTimeImmutable}
     *
     * @param array<int, TRow> $rows
     * @return array<int, TRow>
     */
    private static function within(array $rows, DashboardPeriod $period): array
    {
        return array_filter(
            $rows,
            static fn (array $row): bool => $row['performedAt'] >= $period->start && $row['performedAt'] <= $period->end,
        );
    }
}
