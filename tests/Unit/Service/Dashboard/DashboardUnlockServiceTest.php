<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\User;
use App\Repository\WorkoutRepository;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardState;
use App\Service\Dashboard\DashboardUnlockService;
use PHPUnit\Framework\TestCase;

final class DashboardUnlockServiceTest extends TestCase
{
    public function testNoWorkoutUnlocksNothing(): void
    {
        $workoutRepository = $this->createStub(WorkoutRepository::class);
        $workoutRepository->method('countByUser')->willReturn(0);

        $state = $this->unlockService($workoutRepository)->getStateForUser($this->createStub(User::class));

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

        $state = $this->unlockService($workoutRepository)->getStateForUser($this->createStub(User::class));

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

        $state = $this->unlockService($workoutRepository)->getStateForUser($this->createStub(User::class));

        self::assertTrue($state->lastWorkoutUnlocked);
        self::assertTrue($state->muscleSingleUnlocked);
        self::assertTrue($state->regularityUnlocked);
        self::assertTrue($state->muscleWeekMonthUnlocked);
        self::assertSame(0, $state->workoutsNeededForRegularity);
        self::assertSame(0, $state->workoutsNeededForMuscleWeekMonth);
    }

    /**
     * Comparaison : il faut au moins 2 semaines différentes avec une séance, sinon il n'y a rien à
     * comparer — plusieurs séances la même semaine ne suffisent pas.
     */
    public function testComparisonStaysLockedWhileAllWorkoutsAreInTheSameWeek(): void
    {
        $state = $this->stateFor(['2026-09-28 08:00', '2026-09-30 08:00', '2026-10-04 18:00']);

        self::assertFalse($state->comparisonUnlocked);
        self::assertSame(1, $state->weeksNeededForComparison);
    }

    public function testComparisonUnlocksOnceWorkoutsSpanTwoDifferentWeeks(): void
    {
        $state = $this->stateFor(['2026-09-27 08:00', '2026-09-28 08:00']);

        self::assertTrue($state->comparisonUnlocked);
        self::assertSame(0, $state->weeksNeededForComparison);
    }

    public function testComparisonNeedsTwoWeeksWithoutAnyWorkout(): void
    {
        $state = $this->stateFor([]);

        self::assertFalse($state->comparisonUnlocked);
        self::assertSame(2, $state->weeksNeededForComparison);
    }

    /**
     * @param list<string> $workoutDates
     */
    private function stateFor(array $workoutDates): DashboardState
    {
        $workoutRepository = $this->createStub(WorkoutRepository::class);
        $workoutRepository->method('countByUser')->willReturn(\count($workoutDates));

        return $this->unlockService($workoutRepository, $workoutDates)->getStateForUser($this->createStub(User::class));
    }

    /**
     * @param list<string> $workoutDates
     */
    private function unlockService(WorkoutRepository $workoutRepository, array $workoutDates = []): DashboardUnlockService
    {
        $statsRepository = $this->createStub(WorkoutStatsRepository::class);
        $statsRepository->method('findAllPerformedDatesByUser')->willReturn(
            array_map(static fn (string $date): \DateTimeImmutable => new \DateTimeImmutable($date), $workoutDates),
        );

        return new DashboardUnlockService($workoutRepository, $statsRepository);
    }
}
