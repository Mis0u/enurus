<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview;

use App\Service\YearInReview\YearInReviewCalendar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class YearInReviewCalendarTest extends TestCase
{
    public function testPeriodRunsFromFirstJanuaryToEndOfFifteenthDecember(): void
    {
        $period = $this->calendarAt('2026-12-20 10:00:00 Europe/Paris')->periodOf(2026);

        self::assertEquals(new \DateTimeImmutable('2026-01-01 00:00:00'), $period->start);
        self::assertEquals(new \DateTimeImmutable('2026-12-15 23:59:59'), $period->end);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function publicationMoments(): iterable
    {
        yield 'mid-November' => ['2026-11-10 12:00:00 Europe/Paris', false];
        yield 'last minute of the 15th in Paris' => ['2026-12-15 23:59:59 Europe/Paris', false];
        yield 'midnight of the 16th in Paris' => ['2026-12-16 00:00:00 Europe/Paris', true];
        yield 'midnight in Paris is still the 15th in UTC' => ['2026-12-15 23:00:00 UTC', true];
        yield 'following year' => ['2027-03-01 09:00:00 Europe/Paris', true];
    }

    #[DataProvider('publicationMoments')]
    public function testYearIsPublishedFromMidnightOfSixteenthDecemberInParis(string $now, bool $expected): void
    {
        self::assertSame($expected, $this->calendarAt($now)->isPublished(2026));
    }

    public function testYearBeforeFirstYearIsNeverPublished(): void
    {
        self::assertFalse($this->calendarAt('2030-01-01 00:00:00 Europe/Paris')->isPublished(2025));
    }

    public function testNoYearIsPublishedBeforeTheFirstPublication(): void
    {
        self::assertNull($this->calendarAt('2026-12-15 23:59:59 Europe/Paris')->latestPublishedYear());
    }

    public function testLatestPublishedYearIsCurrentYearFromSixteenthDecember(): void
    {
        self::assertSame(2026, $this->calendarAt('2026-12-16 00:00:00 Europe/Paris')->latestPublishedYear());
    }

    public function testLatestPublishedYearIsPreviousYearBeforeSixteenthDecember(): void
    {
        self::assertSame(2026, $this->calendarAt('2027-12-01 08:00:00 Europe/Paris')->latestPublishedYear());
    }

    private function calendarAt(string $now): YearInReviewCalendar
    {
        return new YearInReviewCalendar(new MockClock(new \DateTimeImmutable($now)));
    }
}
