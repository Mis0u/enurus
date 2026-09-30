<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview;

use App\Service\YearInReview\YearInReviewClockFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class YearInReviewClockFactoryTest extends TestCase
{
    public function testRealClockIsKeptWithoutFakeDate(): void
    {
        $realClock = new MockClock('2026-09-30 10:00:00');

        self::assertSame($realClock, YearInReviewClockFactory::create($realClock, null));
        self::assertSame($realClock, YearInReviewClockFactory::create($realClock, ''));
    }

    public function testFakeDateReplacesRealClock(): void
    {
        $clock = YearInReviewClockFactory::create(new MockClock('2026-09-30 10:00:00'), '2026-12-17 10:00');

        self::assertEquals(new \DateTimeImmutable('2026-12-17 10:00'), $clock->now());
    }
}
