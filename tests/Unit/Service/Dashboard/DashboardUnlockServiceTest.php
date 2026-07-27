<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\User;
use App\Repository\WorkoutRepository;
use App\Service\Dashboard\DashboardUnlockService;
use PHPUnit\Framework\TestCase;

final class DashboardUnlockServiceTest extends TestCase
{
    public function testNoWorkoutUnlocksNothing(): void
    {
        $workoutRepository = $this->createStub(WorkoutRepository::class);
        $workoutRepository->method('countByUser')->willReturn(0);

        $state = (new DashboardUnlockService($workoutRepository))->getStateForUser($this->createStub(User::class));

        self::assertFalse($state->lastWorkoutUnlocked);
        self::assertFalse($state->muscleSingleUnlocked);
        self::assertFalse($state->regularityUnlocked);
        self::assertFalse($state->muscleWeekMonthUnlocked);
        self::assertSame(2, $state->workoutsNeededForRegularity);
        self::assertSame(2, $state->workoutsNeededForMuscleWeekMonth);
    }

    public function testOneWorkoutUnlocksOnlySingleWidgets(): void
    {
        $workoutRepository = $this->createStub(WorkoutRepository::class);
        $workoutRepository->method('countByUser')->willReturn(1);

        $state = (new DashboardUnlockService($workoutRepository))->getStateForUser($this->createStub(User::class));

        self::assertTrue($state->lastWorkoutUnlocked);
        self::assertTrue($state->muscleSingleUnlocked);
        self::assertFalse($state->regularityUnlocked);
        self::assertFalse($state->muscleWeekMonthUnlocked);
        self::assertSame(1, $state->workoutsNeededForRegularity);
        self::assertSame(1, $state->workoutsNeededForMuscleWeekMonth);
    }

    public function testTwoWorkoutsUnlockEverything(): void
    {
        $workoutRepository = $this->createStub(WorkoutRepository::class);
        $workoutRepository->method('countByUser')->willReturn(2);

        $state = (new DashboardUnlockService($workoutRepository))->getStateForUser($this->createStub(User::class));

        self::assertTrue($state->lastWorkoutUnlocked);
        self::assertTrue($state->muscleSingleUnlocked);
        self::assertTrue($state->regularityUnlocked);
        self::assertTrue($state->muscleWeekMonthUnlocked);
        self::assertSame(0, $state->workoutsNeededForRegularity);
        self::assertSame(0, $state->workoutsNeededForMuscleWeekMonth);
    }
}
