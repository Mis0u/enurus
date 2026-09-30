<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Repository\WorkoutTonnageRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\YearInReview\Calculator\YearInReviewHeatmapCalculator;
use PHPUnit\Framework\TestCase;

final class YearInReviewHeatmapCalculatorTest extends TestCase
{
    public function testAddsUpTonnageOfWorkoutsOnTheSameDay(): void
    {
        $tonnageRepository = $this->createStub(WorkoutTonnageRepository::class);
        $tonnageRepository->method('findTonnageSeriesByUser')->willReturn([
            [
                'performedAt' => new \DateTimeImmutable('2026-01-05 08:00'),
                'tonnage' => 4200.0,
            ],
            [
                'performedAt' => new \DateTimeImmutable('2026-01-05 18:00'),
                'tonnage' => 800.0,
            ],
            [
                'performedAt' => new \DateTimeImmutable('2026-01-07 09:00'),
                'tonnage' => 3800.5,
            ],
        ]);

        $tonnageByDay = new YearInReviewHeatmapCalculator($tonnageRepository)->calculate(new User(), $this->period());

        self::assertSame([
            '2026-01-05' => 5000.0,
            '2026-01-07' => 3800.5,
        ], $tonnageByDay);
    }

    private function period(): DashboardPeriod
    {
        return new DashboardPeriod(new \DateTimeImmutable('2026-01-01 00:00:00'), new \DateTimeImmutable('2026-12-15 23:59:59'));
    }
}
