<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\ExerciseGoal;
use App\Entity\User;
use App\Enum\Dashboard\DashboardWidgetEnum;
use App\Repository\ExerciseGoalRepository;
use App\Service\Dashboard\DashboardState;
use App\Service\Dashboard\DashboardWidgetUnlockResolver;
use PHPUnit\Framework\TestCase;

final class DashboardWidgetUnlockResolverTest extends TestCase
{
    public function testSessionTonnageAndMuscleDistributionAreUnlockedFromFirstWorkout(): void
    {
        $unlocked = $this->resolve(workoutCount: 1, hasAnyGoal: false);

        self::assertTrue($unlocked[DashboardWidgetEnum::SESSION->value]);
        self::assertTrue($unlocked[DashboardWidgetEnum::TONNAGE->value]);
        self::assertTrue($unlocked[DashboardWidgetEnum::MUSCLE_DISTRIBUTION->value]);
    }

    public function testRegularityStaysLockedBelowTwoWorkouts(): void
    {
        $unlocked = $this->resolve(workoutCount: 1, hasAnyGoal: false);

        self::assertFalse($unlocked[DashboardWidgetEnum::REGULARITY->value]);
    }

    public function testRegularityUnlocksFromTwoWorkouts(): void
    {
        $unlocked = $this->resolve(workoutCount: 2, hasAnyGoal: false);

        self::assertTrue($unlocked[DashboardWidgetEnum::REGULARITY->value]);
    }

    public function testGoalsWidgetUnlocksOnlyOnceAGoalExists(): void
    {
        self::assertFalse($this->resolve(workoutCount: 5, hasAnyGoal: false)[DashboardWidgetEnum::GOALS->value]);
        self::assertTrue($this->resolve(workoutCount: 5, hasAnyGoal: true)[DashboardWidgetEnum::GOALS->value]);
    }

    public function testBadgesWidgetUnlocksFromFirstWorkout(): void
    {
        self::assertFalse($this->resolve(workoutCount: 0, hasAnyGoal: false)[DashboardWidgetEnum::BADGES->value]);
        self::assertTrue($this->resolve(workoutCount: 1, hasAnyGoal: false)[DashboardWidgetEnum::BADGES->value]);
    }

    /**
     * @return array<string, bool>
     */
    private function resolve(int $workoutCount, bool $hasAnyGoal): array
    {
        $exerciseGoalRepository = $this->createStub(ExerciseGoalRepository::class);
        $exerciseGoalRepository->method('findByOwner')->willReturn($hasAnyGoal ? [$this->createStub(ExerciseGoal::class)] : []);

        return (new DashboardWidgetUnlockResolver($exerciseGoalRepository))
            ->resolve($this->createStub(User::class), new DashboardState(workoutCount: $workoutCount));
    }
}
