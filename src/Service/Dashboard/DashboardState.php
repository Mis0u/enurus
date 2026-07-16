<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

final readonly class DashboardState
{
    private const int WORKOUTS_NEEDED_FOR_REGULARITY = 2;

    private const int WORKOUTS_NEEDED_FOR_MUSCLE_WEEK_MONTH = 2;

    public bool $lastWorkoutUnlocked;

    public bool $muscleSingleUnlocked;

    public bool $regularityUnlocked;

    public bool $muscleWeekMonthUnlocked;

    public int $workoutsNeededForRegularity;

    public int $workoutsNeededForMuscleWeekMonth;

    public function __construct(
        public int $workoutCount,
    ) {
        $this->lastWorkoutUnlocked = 1 <= $workoutCount;
        $this->muscleSingleUnlocked = 1 <= $workoutCount;
        $this->regularityUnlocked = self::WORKOUTS_NEEDED_FOR_REGULARITY <= $workoutCount;
        $this->muscleWeekMonthUnlocked = self::WORKOUTS_NEEDED_FOR_MUSCLE_WEEK_MONTH <= $workoutCount;

        $this->workoutsNeededForRegularity = max(0, self::WORKOUTS_NEEDED_FOR_REGULARITY - $workoutCount);
        $this->workoutsNeededForMuscleWeekMonth = max(0, self::WORKOUTS_NEEDED_FOR_MUSCLE_WEEK_MONTH - $workoutCount);
    }
}
