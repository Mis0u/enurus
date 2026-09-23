<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Repository\WorkoutStatsRepository;
use App\Service\Workout\DeloadPeriodSetService;
use App\Service\Workout\WeeklyStreakCalculator;

final readonly class DashboardRegularityService
{
    private const int DAYS_PER_WEEK = 7;

    public function __construct(
        private WorkoutStatsRepository $workoutStatsRepository,
        private DashboardPeriodCalculator $periodCalculator,
        private DeloadPeriodSetService $deloadPeriodSetService,
        private WeeklyStreakCalculator $weeklyStreakCalculator,
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
            // Même règle que le badge Régularité : un deload relie la série sans l'allonger.
            'streak' => $this->weeklyStreakCalculator->currentStreak($allDates, $deloadWeekSet, $now),
            'bestStreak' => $this->weeklyStreakCalculator->longestStreak($allDates, $deloadWeekSet, $now),
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
}
