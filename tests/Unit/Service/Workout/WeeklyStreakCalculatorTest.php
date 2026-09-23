<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Workout;

use App\Service\Workout\WeeklyStreakCalculator;
use PHPUnit\Framework\TestCase;

final class WeeklyStreakCalculatorTest extends TestCase
{
    // Mercredi : la semaine en cours commence le lundi 2026-09-21.
    private const string NOW = '2026-09-23 10:00:00';

    public function testNoWorkoutGivesZero(): void
    {
        self::assertSame(0, $this->longest([], []));
        self::assertSame(0, $this->current([], []));
    }

    public function testSingleWorkoutGivesOneWeek(): void
    {
        self::assertSame(1, $this->longest(['2026-09-01'], []));
    }

    public function testSeveralWorkoutsInSameWeekCountOnce(): void
    {
        self::assertSame(1, $this->longest(['2026-08-31', '2026-09-02', '2026-09-06'], []));
    }

    public function testConsecutiveWeeksAreCounted(): void
    {
        self::assertSame(3, $this->longest(['2026-08-31', '2026-09-08', '2026-09-17'], []));
    }

    public function testEmptyWeekBreaksTheStreak(): void
    {
        // Semaines du 03/08 et 10/08, trou le 17/08, puis 24/08, 31/08 et 07/09
        $dates = ['2026-08-03', '2026-08-10', '2026-08-24', '2026-08-31', '2026-09-07'];

        self::assertSame(3, $this->longest($dates, []));
    }

    public function testDeloadWeekBridgesTheStreakWithoutCountingItself(): void
    {
        $dates = ['2026-08-03', '2026-08-10', '2026-08-24'];

        self::assertSame(3, $this->longest($dates, ['2026-08-17']));
    }

    public function testDeloadAloneNeverStartsAStreak(): void
    {
        self::assertSame(0, $this->longest([], ['2026-08-03', '2026-08-10']));
        self::assertSame(0, $this->current([], ['2026-09-14', '2026-09-21']));
    }

    public function testBestStreakIsKeptAfterABreak(): void
    {
        $dates = ['2026-06-01', '2026-06-08', '2026-06-15', '2026-06-22', '2026-09-14'];

        self::assertSame(4, $this->longest($dates, []));
    }

    public function testFutureWorkoutsAndDeloadsAreIgnored(): void
    {
        self::assertSame(1, $this->longest(['2026-09-21', '2026-09-28', '2026-10-05'], ['2026-10-12']));
    }

    public function testCurrentStreakIncludesTheCurrentWeek(): void
    {
        self::assertSame(3, $this->current(['2026-09-07', '2026-09-14', '2026-09-22'], []));
    }

    public function testCurrentStreakFallsBackToPreviousWeekWhenCurrentWeekIsStillEmpty(): void
    {
        self::assertSame(2, $this->current(['2026-09-07', '2026-09-14'], []));
    }

    public function testCurrentStreakIsZeroWhenPreviousWeekIsEmptyToo(): void
    {
        self::assertSame(0, $this->current(['2026-09-07'], []));
    }

    public function testCurrentStreakIsBridgedButNotExtendedByADeload(): void
    {
        // Séances les semaines du 31/08 et 07/09, deload la semaine du 14/09, séance cette semaine.
        self::assertSame(3, $this->current(['2026-08-31', '2026-09-07', '2026-09-22'], ['2026-09-14']));
    }

    public function testCurrentStreakHoldsDuringAnOngoingDeloadWeek(): void
    {
        // Deload cette semaine, aucune séance : la série de 2 semaines tient, sans +1.
        self::assertSame(2, $this->current(['2026-09-07', '2026-09-14'], ['2026-09-21']));
    }

    public function testWeekReachingReturnsTheMondayWhereTheStreakFirstHitsTheLength(): void
    {
        $dates = $this->dates(['2026-08-03', '2026-08-10', '2026-08-24', '2026-08-31', '2026-09-07']);

        $monday = (new WeeklyStreakCalculator())->weekReaching($dates, [], 3, new \DateTimeImmutable(self::NOW));

        self::assertSame('2026-09-07', $monday?->format('Y-m-d'));
    }

    public function testWeekReachingCountsDeloadAsABridgeOnly(): void
    {
        $dates = $this->dates(['2026-08-03', '2026-08-10', '2026-08-24']);

        $monday = (new WeeklyStreakCalculator())->weekReaching($dates, [
            '2026-08-17' => true,
        ], 3, new \DateTimeImmutable(self::NOW));

        self::assertSame('2026-08-24', $monday?->format('Y-m-d'));
    }

    public function testWeekReachingIsNullWhenTheLengthIsNeverReached(): void
    {
        $dates = $this->dates(['2026-08-03', '2026-08-10']);

        self::assertNull((new WeeklyStreakCalculator())->weekReaching($dates, [], 3, new \DateTimeImmutable(self::NOW)));
    }

    /**
     * @param list<string> $workoutDays
     * @param list<string> $deloadMondays
     */
    private function longest(array $workoutDays, array $deloadMondays): int
    {
        return (new WeeklyStreakCalculator())->longestStreak($this->dates($workoutDays), array_fill_keys($deloadMondays, true), new \DateTimeImmutable(self::NOW));
    }

    /**
     * @param list<string> $workoutDays
     * @param list<string> $deloadMondays
     */
    private function current(array $workoutDays, array $deloadMondays): int
    {
        return (new WeeklyStreakCalculator())->currentStreak($this->dates($workoutDays), array_fill_keys($deloadMondays, true), new \DateTimeImmutable(self::NOW));
    }

    /**
     * @param list<string> $days
     * @return list<\DateTimeImmutable>
     */
    private function dates(array $days): array
    {
        return array_map(static fn (string $day): \DateTimeImmutable => new \DateTimeImmutable($day . ' 18:00:00'), $days);
    }
}
