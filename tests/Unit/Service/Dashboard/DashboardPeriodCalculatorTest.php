<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Service\Dashboard\DashboardPeriodCalculator;
use PHPUnit\Framework\TestCase;

final class DashboardPeriodCalculatorTest extends TestCase
{
    private DashboardPeriodCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new DashboardPeriodCalculator();
    }

    public function testCurrentWeekStartsOnMondayAndEndsOnSunday(): void
    {
        // Mercredi.
        $now = new \DateTimeImmutable('2026-01-14 10:00:00');

        $week = $this->calculator->currentWeek($now);

        self::assertSame('2026-01-12 00:00:00', $week->start->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-18 23:59:59', $week->end->format('Y-m-d H:i:s'));
    }

    public function testCurrentWeekWhenNowIsAlreadyMonday(): void
    {
        $now = new \DateTimeImmutable('2026-01-12 08:00:00');

        $week = $this->calculator->currentWeek($now);

        self::assertSame('2026-01-12 00:00:00', $week->start->format('Y-m-d H:i:s'));
    }

    public function testPreviousWeekIsExactlySevenDaysBeforeCurrentWeek(): void
    {
        $now = new \DateTimeImmutable('2026-01-14 10:00:00');

        $previousWeek = $this->calculator->previousWeek($now);

        self::assertSame('2026-01-05 00:00:00', $previousWeek->start->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-11 23:59:59', $previousWeek->end->format('Y-m-d H:i:s'));
    }

    public function testCurrentMonthElapsedStopsAtNowNotAtEndOfMonth(): void
    {
        $now = new \DateTimeImmutable('2026-01-14 10:00:00');

        $month = $this->calculator->currentMonthElapsed($now);

        self::assertSame('2026-01-01 00:00:00', $month->start->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-14 23:59:59', $month->end->format('Y-m-d H:i:s'));
    }

    public function testPreviousMonthCoversTheFullCalendarMonth(): void
    {
        $now = new \DateTimeImmutable('2026-01-14 10:00:00');

        $previousMonth = $this->calculator->previousMonth($now);

        self::assertSame('2025-12-01 00:00:00', $previousMonth->start->format('Y-m-d H:i:s'));
        self::assertSame('2025-12-31 23:59:59', $previousMonth->end->format('Y-m-d H:i:s'));
    }

    public function testCurrentYearElapsedStopsAtNowNotAtEndOfYear(): void
    {
        $now = new \DateTimeImmutable('2026-03-05 10:00:00');

        $year = $this->calculator->currentYearElapsed($now);

        self::assertSame('2026-01-01 00:00:00', $year->start->format('Y-m-d H:i:s'));
        self::assertSame('2026-03-05 23:59:59', $year->end->format('Y-m-d H:i:s'));
    }

    public function testPreviousYearCoversTheFullCalendarYear(): void
    {
        $now = new \DateTimeImmutable('2026-03-05 10:00:00');

        $previousYear = $this->calculator->previousYear($now);

        self::assertSame('2025-01-01 00:00:00', $previousYear->start->format('Y-m-d H:i:s'));
        self::assertSame('2025-12-31 23:59:59', $previousYear->end->format('Y-m-d H:i:s'));
    }

    public function testWeekStartOfReturnsMondayMidnightForAnyDayOfTheWeek(): void
    {
        $sunday = new \DateTimeImmutable('2026-01-18 23:30:00');

        $weekStart = $this->calculator->weekStartOf($sunday);

        self::assertSame('2026-01-12 00:00:00', $weekStart->format('Y-m-d H:i:s'));
    }
}
