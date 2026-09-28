<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

final readonly class DashboardState
{
    private const int WORKOUTS_NEEDED_FOR_REGULARITY = 2;

    private const int WORKOUTS_NEEDED_FOR_MUSCLE_WEEK_MONTH = 2;

    // Semaines différentes avec au moins une séance : sans deux semaines, rien à comparer.
    private const int TRAINED_WEEKS_NEEDED_FOR_COMPARISON = 2;

    public bool $lastWorkoutUnlocked;

    public bool $muscleSingleUnlocked;

    public bool $regularityUnlocked;

    public bool $muscleWeekMonthUnlocked;

    public int $workoutsNeededForRegularity;

    public int $workoutsNeededForMuscleWeekMonth;

    public bool $comparisonUnlocked;

    public int $weeksNeededForComparison;

    public function __construct(
        public int $workoutCount,
        public int $trainedWeekCount = 0,
    ) {
        $this->lastWorkoutUnlocked = 1 <= $workoutCount;
        $this->muscleSingleUnlocked = 1 <= $workoutCount;
        $this->regularityUnlocked = self::WORKOUTS_NEEDED_FOR_REGULARITY <= $workoutCount;
        $this->muscleWeekMonthUnlocked = self::WORKOUTS_NEEDED_FOR_MUSCLE_WEEK_MONTH <= $workoutCount;

        $this->workoutsNeededForRegularity = max(0, self::WORKOUTS_NEEDED_FOR_REGULARITY - $workoutCount);
        $this->workoutsNeededForMuscleWeekMonth = max(0, self::WORKOUTS_NEEDED_FOR_MUSCLE_WEEK_MONTH - $workoutCount);
        $this->comparisonUnlocked = self::TRAINED_WEEKS_NEEDED_FOR_COMPARISON <= $trainedWeekCount;
        $this->weeksNeededForComparison = max(0, self::TRAINED_WEEKS_NEEDED_FOR_COMPARISON - $trainedWeekCount);
    }
}
