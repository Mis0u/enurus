<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Repository\WorkoutStatsRepository;
use App\Service\Workout\DeloadPeriodSetService;

final readonly class DashboardRegularityService
{
    private const int SECONDS_PER_DAY = 86400;

    private const int DAYS_PER_WEEK = 7;

    public function __construct(
        private WorkoutStatsRepository $workoutStatsRepository,
        private DashboardPeriodCalculator $periodCalculator,
        private DeloadPeriodSetService $deloadPeriodSetService,
    ) {
    }

    /**
     * @return array{
     *     streak: int,
     *     bestStreak: int,
     *     weekCount: int,
     *     monthCount: int,
     *     yearCount: int,
     *     weekDelta: int,
     *     monthDelta: int,
     *     yearDelta: int|null,
     *     weekDays: array<int, array{date: \DateTimeImmutable, hasWorkout: bool, isToday: bool, isFuture: bool, isDeload: bool}>,
     *     previousWeekDays: array<int, array{date: \DateTimeImmutable, hasWorkout: bool, isToday: bool, isFuture: bool, isDeload: bool}>
     * }
     */
    public function getData(User $user): array
    {
        $deloadWeekSet = $this->deloadPeriodSetService->weekKeySet($user);
        $deloadDaySet = $this->deloadPeriodSetService->dayKeySet($user);
        $now = new \DateTimeImmutable();
        $today = $now->format('Y-m-d');

        $week = $this->periodCalculator->currentWeek($now);
        $previousWeek = $this->periodCalculator->previousWeek($now);
        $month = $this->periodCalculator->currentMonthElapsed($now);
        $previousMonth = $this->periodCalculator->previousMonth($now);

        // Année calendaire en cours (1er janvier → aujourd'hui) — même définition que le widget
        // Tonnage soulevé, voir docs/dashboard-architecture.md.
        $year = $this->periodCalculator->currentYearElapsed($now);
        $previousYear = $this->periodCalculator->previousYear($now);

        $allDates = $this->workoutStatsRepository->findAllPerformedDatesByUser($user);

        $weekCount = 0;
        $monthCount = 0;
        $yearCount = 0;
        $workoutDaySet = [];

        foreach ($allDates as $date) {
            if ($date >= $week->start && $date <= $week->end) {
                $weekCount++;
                $workoutDaySet[$date->format('Y-m-d')] = true;
            }

            if ($date >= $previousWeek->start && $date <= $previousWeek->end) {
                $workoutDaySet[$date->format('Y-m-d')] = true;
            }

            if ($date >= $month->start && $date <= $month->end) {
                $monthCount++;
            }

            if ($date >= $year->start && $date <= $year->end) {
                $yearCount++;
            }
        }

        $previousWeekCount = $this->workoutStatsRepository->countByUserAndDate($user, $previousWeek->start, $previousWeek->end);
        $previousMonthCount = $this->workoutStatsRepository->countByUserAndDate($user, $previousMonth->start, $previousMonth->end);
        $previousYearCount = $this->workoutStatsRepository->countByUserAndDate($user, $previousYear->start, $previousYear->end);

        return [
            'streak' => $this->computeStreak($allDates, $week->start, $deloadWeekSet),
            'bestStreak' => $this->computeBestStreak($allDates, $deloadWeekSet),
            'weekCount' => $weekCount,
            'monthCount' => $monthCount,
            'yearCount' => $yearCount,
            'weekDelta' => $weekCount - $previousWeekCount,
            'monthDelta' => $monthCount - $previousMonthCount,
            // null si aucune séance l'année précédente — comparer à zéro n'aurait pas de sens informatif.
            'yearDelta' => 0 < $previousYearCount ? $yearCount - $previousYearCount : null,
            'weekDays' => $this->buildWeekDays($week->start, $workoutDaySet, $today, $deloadDaySet),
            'previousWeekDays' => $this->buildWeekDays($previousWeek->start, $workoutDaySet, $today, $deloadDaySet),
        ];
    }

    /**
     * Une semaine couverte par un deload (même sans séance) ne casse jamais la série — voir
     * `App\Entity\DeloadPeriod`. `$deloadWeekSet` est déjà indexé par lundi `Y-m-d`, même clé que
     * `$currentWeekStart` (toujours un lundi, cf. `DashboardPeriodCalculator::weekStartOf()`).
     *
     * @param \DateTimeImmutable[] $allDates
     * @param array<string, true> $deloadWeekSet
     */
    private function computeStreak(array $allDates, \DateTimeImmutable $currentWeekStart, array $deloadWeekSet): int
    {
        if ([] === $allDates && [] === $deloadWeekSet) {
            return 0;
        }

        $weekSet = $deloadWeekSet;
        foreach ($allDates as $date) {
            $weekSet[self::mondayKeyOf($date)] = true;
        }

        $week = $currentWeekStart;

        // If current week has no workout, start counting from previous week
        if (! isset($weekSet[$week->format('Y-m-d')])) {
            $week = $week->modify('-7 days');
        }

        $streak = 0;
        while (isset($weekSet[$week->format('Y-m-d')])) {
            $streak++;
            $week = $week->modify('-7 days');
        }

        return $streak;
    }

    /**
     * Record — plus long streak (semaines consécutives avec au moins une séance, ou couvertes par
     * un deload) sur tout l'historique, streak en cours inclus s'il en fait partie.
     *
     * @param \DateTimeImmutable[] $allDates
     * @param array<string, true> $deloadWeekSet
     */
    private function computeBestStreak(array $allDates, array $deloadWeekSet): int
    {
        if ([] === $allDates && [] === $deloadWeekSet) {
            return 0;
        }

        /** @var array<string, \DateTimeImmutable> $weekStarts */
        $weekStarts = [];
        foreach ($allDates as $date) {
            $monday = $date->modify(sprintf('-%d days', ((int) $date->format('N')) - 1))->setTime(0, 0, 0);
            $weekStarts[$monday->format('Y-m-d')] = $monday;
        }

        foreach (array_keys($deloadWeekSet) as $mondayKey) {
            $weekStarts[$mondayKey] ??= new \DateTimeImmutable($mondayKey);
        }

        $sorted = array_values($weekStarts);
        usort($sorted, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $a <=> $b);

        $best = 1;
        $current = 1;

        for ($i = 1, $count = \count($sorted); $i < $count; $i++) {
            $diffDays = (int) round(($sorted[$i]->getTimestamp() - $sorted[$i - 1]->getTimestamp()) / self::SECONDS_PER_DAY);

            if (self::DAYS_PER_WEEK === $diffDays) {
                $current++;
                $best = max($best, $current);
            } else {
                $current = 1;
            }
        }

        return $best;
    }

    /**
     * @param array<string, bool> $workoutDaySet
     * @param array<string, true> $deloadDaySet
     * @return array<int, array{date: \DateTimeImmutable, hasWorkout: bool, isToday: bool, isFuture: bool, isDeload: bool}>
     */
    private function buildWeekDays(\DateTimeImmutable $weekStart, array $workoutDaySet, string $today, array $deloadDaySet): array
    {
        $days = [];
        for ($i = 0; self::DAYS_PER_WEEK > $i; $i++) {
            $day = $weekStart->modify(sprintf('+%d days', $i));
            $dayStr = $day->format('Y-m-d');
            $days[] = [
                'date' => $day,
                'hasWorkout' => isset($workoutDaySet[$dayStr]),
                'isToday' => $dayStr === $today,
                'isFuture' => $dayStr > $today,
                'isDeload' => isset($deloadDaySet[$dayStr]),
            ];
        }

        return $days;
    }

    private static function mondayKeyOf(\DateTimeImmutable $date): string
    {
        return $date->modify(sprintf('-%d days', ((int) $date->format('N')) - 1))->format('Y-m-d');
    }
}
