<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Repository\WorkoutStatsRepository;
use App\Repository\WorkoutTonnageRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\YearInReview\Calculator\YearInReviewTotalsCalculator;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;
use PHPUnit\Framework\TestCase;

final class YearInReviewTotalsCalculatorTest extends TestCase
{
    public function testSumsWorkoutsSetsRepsAndTonnageOfThePeriod(): void
    {
        $statsRepository = $this->createStub(WorkoutStatsRepository::class);
        $statsRepository->method('findSetAndRepTotalsPerWorkout')->willReturn([
            [
                'performedAt' => new \DateTimeImmutable('2026-01-05'),
                'sets' => 12,
                'reps' => 96,
            ],
            [
                'performedAt' => new \DateTimeImmutable('2026-01-07'),
                'sets' => 15,
                'reps' => 120,
            ],
        ]);
        $tonnageRepository = $this->createStub(WorkoutTonnageRepository::class);
        $tonnageRepository->method('findTonnageSeriesByUser')->willReturn([
            [
                'performedAt' => new \DateTimeImmutable('2026-01-05'),
                'tonnage' => 4200.0,
            ],
            [
                'performedAt' => new \DateTimeImmutable('2026-01-07'),
                'tonnage' => 3800.5,
            ],
        ]);

        $totals = new YearInReviewTotalsCalculator($statsRepository, $tonnageRepository)->calculate(new User(), $this->period());

        self::assertEquals(new YearInReviewTotals(workoutCount: 2, setCount: 27, repCount: 216, tonnageKg: 8000.5), $totals);
    }

    private function period(): DashboardPeriod
    {
        return new DashboardPeriod(new \DateTimeImmutable('2026-01-01 00:00:00'), new \DateTimeImmutable('2026-12-15 23:59:59'));
    }
}
