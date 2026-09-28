<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard\Comparison;

use App\Service\Dashboard\Comparison\ComparisonSources;
use App\Service\Dashboard\DashboardPeriod;
use PHPUnit\Framework\TestCase;

final class ComparisonSourcesTest extends TestCase
{
    private const string PERIOD_START = '2026-09-01 00:00:00';

    private const string PERIOD_END = '2026-09-28 23:59:59';

    public function testEachMetricAddsUpTheWorkoutsOfThePeriodOnly(): void
    {
        $sources = new ComparisonSources(
            [
                $this->totals('2026-08-31 20:00', sets: 9, reps: 90),
                $this->totals('2026-09-01 08:00', sets: 10, reps: 80),
                $this->totals('2026-09-28 23:00', sets: 5, reps: 40),
                $this->totals('2026-09-29 07:00', sets: 9, reps: 90),
            ],
            [
                [
                    'performedAt' => new \DateTimeImmutable('2026-09-01 08:00'),
                    'tonnage' => 1500.0,
                ],
                [
                    'performedAt' => new \DateTimeImmutable('2026-09-28 23:00'),
                    'tonnage' => 500.5,
                ],
                [
                    'performedAt' => new \DateTimeImmutable('2026-09-29 07:00'),
                    'tonnage' => 9999.0,
                ],
            ],
            [
                [
                    'workoutId' => 'a',
                    'performedAt' => new \DateTimeImmutable('2026-09-01 08:00'),
                ],
                [
                    'workoutId' => 'b',
                    'performedAt' => new \DateTimeImmutable('2026-08-31 20:00'),
                ],
            ],
            [],
        );
        $period = new DashboardPeriod(new \DateTimeImmutable(self::PERIOD_START), new \DateTimeImmutable(self::PERIOD_END));

        self::assertSame(2, $sources->sessionCountIn($period));
        self::assertSame(15, $sources->setCountIn($period));
        self::assertSame(120, $sources->repCountIn($period));
        self::assertSame(2000.5, $sources->tonnageKgIn($period));
        self::assertSame(1, $sources->prCountIn($period));
    }

    public function testAPeriodIncludesADeloadWhenOneOfItsDaysIsCovered(): void
    {
        $period = new DashboardPeriod(new \DateTimeImmutable(self::PERIOD_START), new \DateTimeImmutable(self::PERIOD_END));

        self::assertTrue(new ComparisonSources([], [], [], [
            '2026-09-28' => true,
        ])->includesDeload($period));
        self::assertFalse(new ComparisonSources([], [], [], [
            '2026-09-29' => true,
        ])->includesDeload($period));
    }

    /**
     * @return array{performedAt: \DateTimeImmutable, sets: int, reps: int}
     */
    private function totals(string $performedAt, int $sets, int $reps): array
    {
        return [
            'performedAt' => new \DateTimeImmutable($performedAt),
            'sets' => $sets,
            'reps' => $reps,
        ];
    }
}
