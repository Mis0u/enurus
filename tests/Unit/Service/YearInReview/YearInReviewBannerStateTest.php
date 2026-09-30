<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview;

use App\Entity\User;
use App\Entity\YearInReview;
use App\Repository\YearInReviewRepository;
use App\Service\YearInReview\Snapshot\YearInReviewRecords;
use App\Service\YearInReview\Snapshot\YearInReviewRegularity;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;
use App\Service\YearInReview\YearInReviewBannerState;
use App\Service\YearInReview\YearInReviewCalendar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class YearInReviewBannerStateTest extends TestCase
{
    private const string DURING_THE_BANNER = '2027-01-10 10:00:00 Europe/Paris';

    public function testUnopenedEligibleReviewIsAnnouncedOnTheDashboard(): void
    {
        self::assertSame(2026, $this->state(self::DURING_THE_BANNER, $this->review())->yearToShow(new User()));
    }

    public function testNothingOnceFebruaryHasStarted(): void
    {
        self::assertNull($this->state('2027-02-01 00:00:00 Europe/Paris', $this->review())->yearToShow(new User()));
    }

    public function testNothingOnceTheReviewWasOpened(): void
    {
        $review = $this->review();
        $review->seenAt = new \DateTimeImmutable('2026-12-16 08:00:00');

        self::assertNull($this->state(self::DURING_THE_BANNER, $review)->yearToShow(new User()));
    }

    public function testNothingOnceTheBannerWasDismissed(): void
    {
        $review = $this->review();
        $review->bannerDismissedAt = new \DateTimeImmutable('2026-12-17 08:00:00');

        self::assertNull($this->state(self::DURING_THE_BANNER, $review)->yearToShow(new User()));
    }

    public function testNothingWithoutAnEligibleReview(): void
    {
        self::assertNull($this->state(self::DURING_THE_BANNER, null)->yearToShow(new User()));
    }

    private function state(string $now, ?YearInReview $eligibleReview): YearInReviewBannerState
    {
        $repository = $this->createStub(YearInReviewRepository::class);
        $repository->method('findEligibleByOwnerAndYear')->willReturn($eligibleReview);

        return new YearInReviewBannerState(new YearInReviewCalendar(new MockClock(new \DateTimeImmutable($now))), $repository);
    }

    private function review(): YearInReview
    {
        return YearInReview::eligible(new User(), 2026, new YearInReviewSnapshot(
            new YearInReviewTotals(12, 36, 360, 5400.0),
            [],
            [],
            null,
            new YearInReviewRecords(0, null),
            new YearInReviewRegularity(2, 3, 5),
            [],
            null,
        ));
    }
}
