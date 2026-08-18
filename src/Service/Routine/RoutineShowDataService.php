<?php

declare(strict_types=1);

namespace App\Service\Routine;

use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Entity\User;
use App\Repository\RoutineMuscleRepository;
use App\Repository\RoutineStatsRepository;
use App\Service\Dashboard\DashboardPeriodCalculator;

/**
 * Assemble les données de la page de détail d'une routine : liste ordonnée des exercices,
 * muscles sollicités (silhouette), et statistiques d'usage (nombre de séances basées sur cette
 * routine, dernière utilisation, durée moyenne, répartition semaine/mois/année) — dérivées de
 * `Workout::routine`, `RoutineExercise` ne portant lui-même aucune donnée d'exécution.
 */
final readonly class RoutineShowDataService
{
    public function __construct(
        private RoutineMuscleRepository $routineMuscleRepository,
        private RoutineStatsRepository $routineStatsRepository,
        private DashboardPeriodCalculator $periodCalculator,
    ) {
    }

    /**
     * @return array{
     *     routineExercises: list<RoutineExercise>,
     *     allPrimarySvgIds: list<string>,
     *     allSecondarySvgIds: list<string>,
     *     hasUsage: bool,
     *     usageCount: int,
     *     lastUsedAt: ?\DateTimeImmutable,
     *     averageDurationMinutes: ?int,
     *     weekCount: int,
     *     monthCount: int,
     *     yearCount: int,
     * }
     */
    public function build(Routine $routine, User $user): array
    {
        $svgIds = $this->routineMuscleRepository->findSvgIdsByRoutine($routine);
        $usageDates = $this->routineStatsRepository->findUsageDatesByRoutine($routine, $user);
        $hasUsage = [] !== $usageDates;

        return [
            'routineExercises' => $this->sortByPosition($routine->routineExercises->toArray()),
            'allPrimarySvgIds' => $svgIds['primary'],
            'allSecondarySvgIds' => $svgIds['secondary'],
            'hasUsage' => $hasUsage,
            'usageCount' => \count($usageDates),
            'lastUsedAt' => $this->routineStatsRepository->lastUsedAtFromDates($usageDates),
            'averageDurationMinutes' => $this->averageDurationMinutes($routine, $user),
            ...$this->periodCounts($usageDates),
        ];
    }

    /**
     * @param array<RoutineExercise> $routineExercises
     * @return list<RoutineExercise>
     */
    private function sortByPosition(array $routineExercises): array
    {
        usort($routineExercises, static fn (RoutineExercise $a, RoutineExercise $b): int => $a->position <=> $b->position);

        return $routineExercises;
    }

    private function averageDurationMinutes(Routine $routine, User $user): ?int
    {
        $average = $this->routineStatsRepository->averageDurationByRoutine($routine, $user);

        if (null === $average) {
            return null;
        }

        return (int) round($average);
    }

    /**
     * @param list<\DateTimeImmutable> $usageDates
     * @return array{weekCount: int, monthCount: int, yearCount: int}
     */
    private function periodCounts(array $usageDates): array
    {
        $now = new \DateTimeImmutable();
        $week = $this->periodCalculator->currentWeek($now);
        $month = $this->periodCalculator->currentMonthElapsed($now);
        $year = $this->periodCalculator->currentYearElapsed($now);

        return [
            'weekCount' => $this->countWithin($usageDates, $week->start, $week->end),
            'monthCount' => $this->countWithin($usageDates, $month->start, $month->end),
            'yearCount' => $this->countWithin($usageDates, $year->start, $year->end),
        ];
    }

    /**
     * @param list<\DateTimeImmutable> $usageDates
     */
    private function countWithin(array $usageDates, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return \count(array_filter(
            $usageDates,
            static fn (\DateTimeImmutable $date): bool => $date >= $start && $date <= $end,
        ));
    }
}
