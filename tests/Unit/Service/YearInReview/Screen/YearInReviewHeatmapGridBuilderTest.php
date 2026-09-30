<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview\Screen;

use App\Service\Dashboard\DashboardPeriod;
use App\Service\Workout\HeatmapLevelCalculator;
use App\Service\YearInReview\Screen\YearInReviewHeatmapGridBuilder;
use PHPUnit\Framework\TestCase;

final class YearInReviewHeatmapGridBuilderTest extends TestCase
{
    public function testGridCoversWholeWeeksFromJanuaryFirstToDecemberFifteenth(): void
    {
        $weeks = $this->grid([])['weeks'];

        self::assertCount(51, $weeks);
        self::assertSame('2025-12-29', $weeks[0]['days'][0]['date']->format('Y-m-d'));
        self::assertSame('2026-12-20', $weeks[50]['days'][6]['date']->format('Y-m-d'));
    }

    public function testDaysWithWorkoutsGetTheirRelativeLevel(): void
    {
        $weeks = $this->grid([
            '2026-01-05' => 1000.0,
            '2026-01-07' => 5000.0,
            '2026-01-09' => 9000.0,
        ])['weeks'];

        self::assertSame(HeatmapLevelCalculator::LEVEL_LIGHT, $weeks[1]['days'][0]['level']);
        self::assertSame(HeatmapLevelCalculator::LEVEL_INTENSE, $weeks[1]['days'][4]['level']);
        self::assertSame(0, $weeks[1]['days'][1]['level']);
    }

    public function testOnlyTheFirstWeekOfEachQuarterShowsItsMonthNeverTheLeadingDecemberWeek(): void
    {
        $labelledMondays = array_map(
            static fn (array $week): string => $week['days'][0]['date']->format('Y-m-d'),
            array_values(array_filter($this->grid([])['weeks'], static fn (array $week): bool => $week['showsMonthLabel'])),
        );

        self::assertSame(['2026-01-05', '2026-04-06', '2026-07-06', '2026-10-05'], $labelledMondays);
    }

    /**
     * @param array<string, float> $tonnageKgByDay
     *
     * @return array{weeks: list<array{days: list<array{date: \DateTimeImmutable, level: int, isDeload: bool}>, showsMonthLabel: bool}>}
     */
    private function grid(array $tonnageKgByDay): array
    {
        $period = new DashboardPeriod(new \DateTimeImmutable('2026-01-01 00:00:00'), new \DateTimeImmutable('2026-12-15 23:59:59'));

        return new YearInReviewHeatmapGridBuilder(new HeatmapLevelCalculator())->build($tonnageKgByDay, $period);
    }
}
