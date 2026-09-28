<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard\Comparison;

use App\Enum\Dashboard\ComparisonGranularityEnum;
use App\Service\Dashboard\Comparison\ComparisonPeriodCalculator;
use App\Service\Dashboard\Comparison\ComparisonPeriods;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * « À période égale » : du début de la période à aujourd'hui, comparé aux mêmes jours de la période
 * précédente — borné à la fin de celle-ci quand le jour n'y existe pas (31 oct. → 30 sept.).
 */
final class ComparisonPeriodCalculatorTest extends TestCase
{
    /**
     * @return array<string, array{ComparisonGranularityEnum, string, string, string, string, string}>
     */
    public static function periodProvider(): array
    {
        return [
            'week, a Wednesday' => [ComparisonGranularityEnum::WEEK, '2026-09-30', '2026-09-28', '2026-09-30', '2026-09-21', '2026-09-23'],
            'week, a Monday' => [ComparisonGranularityEnum::WEEK, '2026-09-28', '2026-09-28', '2026-09-28', '2026-09-21', '2026-09-21'],
            'month, mid-month' => [ComparisonGranularityEnum::MONTH, '2026-10-28', '2026-10-01', '2026-10-28', '2026-09-01', '2026-09-28'],
            'month, 31st after a 30-day month' => [ComparisonGranularityEnum::MONTH, '2026-10-31', '2026-10-01', '2026-10-31', '2026-09-01', '2026-09-30'],
            'month, 30 March after February' => [ComparisonGranularityEnum::MONTH, '2027-03-30', '2027-03-01', '2027-03-30', '2027-02-01', '2027-02-28'],
            'month, January after December' => [ComparisonGranularityEnum::MONTH, '2027-01-15', '2027-01-01', '2027-01-15', '2026-12-01', '2026-12-15'],
            'year, same dates last year' => [ComparisonGranularityEnum::YEAR, '2026-09-28', '2026-01-01', '2026-09-28', '2025-01-01', '2025-09-28'],
            'year, 29 February of a leap year' => [ComparisonGranularityEnum::YEAR, '2028-02-29', '2028-01-01', '2028-02-29', '2027-01-01', '2027-02-28'],
        ];
    }

    #[DataProvider('periodProvider')]
    public function testComparesUpToTodayWithTheSameDaysOfThePreviousPeriod(
        ComparisonGranularityEnum $granularity,
        string $today,
        string $currentStart,
        string $currentEnd,
        string $previousStart,
        string $previousEnd,
    ): void {
        $periods = (new ComparisonPeriodCalculator())->calculate($granularity, new \DateTimeImmutable($today . ' 15:42'));

        self::assertSame([$currentStart, $currentEnd, $previousStart, $previousEnd], $this->days($periods));
    }

    /**
     * Bornes incluses sur toute la journée : une séance du soir le dernier jour compte.
     */
    public function testPeriodsCoverWholeDays(): void
    {
        $periods = (new ComparisonPeriodCalculator())->calculate(ComparisonGranularityEnum::MONTH, new \DateTimeImmutable('2026-10-31 08:00'));

        self::assertSame('00:00:00', $periods->current->start->format('H:i:s'));
        self::assertSame('23:59:59', $periods->current->end->format('H:i:s'));
        self::assertSame('23:59:59', $periods->previous->end->format('H:i:s'));
    }

    /**
     * @return list<string>
     */
    private function days(ComparisonPeriods $periods): array
    {
        return [
            $periods->current->start->format('Y-m-d'),
            $periods->current->end->format('Y-m-d'),
            $periods->previous->start->format('Y-m-d'),
            $periods->previous->end->format('Y-m-d'),
        ];
    }
}
