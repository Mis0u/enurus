<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Repository\WorkoutMuscleRepository;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\YearInReview\Calculator\YearInReviewTopMuscleCalculator;
use App\Service\YearInReview\Snapshot\YearInReviewMuscle;
use PHPUnit\Framework\TestCase;

final class YearInReviewTopMuscleCalculatorTest extends TestCase
{
    public function testMuscleWithMostPrimarySetsWins(): void
    {
        $calculator = $this->calculator([
            [
                'id' => 'shoulders',
                'name' => 'muscle.shoulders',
                'sets' => 300,
                'primarySets' => 40,
                'secondarySets' => 260,
            ],
            [
                'id' => 'chest',
                'name' => 'muscle.chest',
                'sets' => 200,
                'primarySets' => 180,
                'secondarySets' => 20,
            ],
        ]);

        self::assertEquals(new YearInReviewMuscle('chest', 180), $calculator->calculate(new User(), $this->period()));
    }

    public function testNoMuscleWithoutPrimarySets(): void
    {
        $calculator = $this->calculator([
            [
                'id' => 'shoulders',
                'name' => 'muscle.shoulders',
                'sets' => 30,
                'primarySets' => 0,
                'secondarySets' => 30,
            ],
        ]);

        self::assertNull($calculator->calculate(new User(), $this->period()));
    }

    /**
     * @param array<int, array{id: string, name: string, sets: int, primarySets: int, secondarySets: int}> $setCounts
     */
    private function calculator(array $setCounts): YearInReviewTopMuscleCalculator
    {
        $statsRepository = $this->createStub(WorkoutStatsRepository::class);
        $statsRepository->method('findIdsByUserAndDateRange')->willReturn(['workout-1']);
        $muscleRepository = $this->createStub(WorkoutMuscleRepository::class);
        $muscleRepository->method('findMuscleGroupSetCountsByWorkoutIds')->willReturn($setCounts);

        return new YearInReviewTopMuscleCalculator($statsRepository, $muscleRepository);
    }

    private function period(): DashboardPeriod
    {
        return new DashboardPeriod(new \DateTimeImmutable('2026-01-01 00:00:00'), new \DateTimeImmutable('2026-12-15 23:59:59'));
    }
}
