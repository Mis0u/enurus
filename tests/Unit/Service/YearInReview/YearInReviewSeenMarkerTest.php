<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview;

use App\Entity\User;
use App\Entity\YearInReview;
use App\Service\YearInReview\YearInReviewSeenMarker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class YearInReviewSeenMarkerTest extends TestCase
{
    public function testFirstOpeningIsRecorded(): void
    {
        $review = YearInReview::notEligible(new User(), 2026, 3);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        new YearInReviewSeenMarker($em, new MockClock('2026-12-16 08:00:00'))->markSeen($review);

        self::assertEquals(new \DateTimeImmutable('2026-12-16 08:00:00'), $review->seenAt);
    }

    public function testLaterOpeningsKeepTheFirstOne(): void
    {
        $review = YearInReview::notEligible(new User(), 2026, 3);
        $review->seenAt = new \DateTimeImmutable('2026-12-16 08:00:00');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        new YearInReviewSeenMarker($em, new MockClock('2026-12-20 10:00:00'))->markSeen($review);

        self::assertEquals(new \DateTimeImmutable('2026-12-16 08:00:00'), $review->seenAt);
    }
}
