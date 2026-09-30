<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\YearInReview\Calculator\YearInReviewTopExercisesCalculator;
use App\Service\YearInReview\Snapshot\YearInReviewExercise;
use PHPUnit\Framework\TestCase;

final class YearInReviewTopExercisesCalculatorTest extends TestCase
{
    public function testAsksForThreeExercisesAndKeepsTheirOrder(): void
    {
        $statsRepository = $this->createMock(WorkoutStatsRepository::class);
        $statsRepository->expects(self::once())
            ->method('findMostFrequentExercisesInRange')
            ->with(self::isInstanceOf(User::class), self::anything(), self::anything(), 3)
            ->willReturn([
                [
                    'name' => 'exercise.bench_press',
                    'isPublic' => true,
                    'workoutCount' => 96,
                ],
                [
                    'name' => 'Mon squat',
                    'isPublic' => false,
                    'workoutCount' => 71,
                ],
            ]);

        $topExercises = new YearInReviewTopExercisesCalculator($statsRepository)->calculate(new User(), $this->period());

        self::assertEquals([
            new YearInReviewExercise('exercise.bench_press', true, 96),
            new YearInReviewExercise('Mon squat', false, 71),
        ], $topExercises);
    }

    private function period(): DashboardPeriod
    {
        return new DashboardPeriod(new \DateTimeImmutable('2026-01-01 00:00:00'), new \DateTimeImmutable('2026-12-15 23:59:59'));
    }
}
