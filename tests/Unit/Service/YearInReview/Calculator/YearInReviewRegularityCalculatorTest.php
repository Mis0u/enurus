<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\Workout\DeloadPeriodSetService;
use App\Service\Workout\WeeklyStreakCalculator;
use App\Service\YearInReview\Calculator\YearInReviewRegularityCalculator;
use App\Service\YearInReview\Snapshot\YearInReviewRegularity;
use PHPUnit\Framework\TestCase;

final class YearInReviewRegularityCalculatorTest extends TestCase
{
    public function testLongestStreakAndBusiestMonthIgnoreWorkoutsOutsideThePeriod(): void
    {
        $calculator = $this->calculator([
            // Fin 2025 : collée à janvier, elle allongerait la série à 5 semaines si elle comptait.
            '2025-12-22', '2025-12-29',
            // 3 semaines consécutives en janvier (5, 12, 19).
            '2026-01-05', '2026-01-12', '2026-01-19',
            // Mars, mois le plus chargé : 4 séances sur 2 semaines non consécutives.
            '2026-03-02', '2026-03-03', '2026-03-04', '2026-03-16',
            // Après le 15 décembre : ne doit pas faire de décembre le mois le plus chargé.
            '2026-12-16', '2026-12-17', '2026-12-18', '2026-12-19', '2026-12-20',
        ]);

        self::assertEquals(
            new YearInReviewRegularity(longestStreakWeeks: 3, busiestMonth: 3, busiestMonthWorkoutCount: 4),
            $calculator->calculate(new User(), $this->period()),
        );
    }

    public function testEarliestMonthWinsATie(): void
    {
        $calculator = $this->calculator(['2026-02-02', '2026-02-04', '2026-05-04', '2026-05-06']);

        self::assertSame(2, $calculator->calculate(new User(), $this->period())->busiestMonth);
    }

    /**
     * @param list<string> $days
     */
    private function calculator(array $days): YearInReviewRegularityCalculator
    {
        $statsRepository = $this->createStub(WorkoutStatsRepository::class);
        $statsRepository->method('findAllPerformedDatesByUser')->willReturn(
            array_map(static fn (string $day): \DateTimeImmutable => new \DateTimeImmutable($day . ' 10:00:00'), $days),
        );
        $deloadPeriodSetService = $this->createStub(DeloadPeriodSetService::class);
        $deloadPeriodSetService->method('weekKeySet')->willReturn([]);

        return new YearInReviewRegularityCalculator($statsRepository, $deloadPeriodSetService, new WeeklyStreakCalculator());
    }

    private function period(): DashboardPeriod
    {
        return new DashboardPeriod(new \DateTimeImmutable('2026-01-01 00:00:00'), new \DateTimeImmutable('2026-12-15 23:59:59'));
    }
}
