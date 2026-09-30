<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview;

use App\Entity\User;
use App\Entity\YearInReview;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\YearInReview\YearInReviewCardStateEnum;
use App\Repository\YearInReviewRepository;
use App\Service\YearInReview\Snapshot\YearInReviewRecords;
use App\Service\YearInReview\Snapshot\YearInReviewRegularity;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;
use App\Service\YearInReview\View\YearInReviewCard;
use App\Service\YearInReview\YearInReviewCalendar;
use App\Service\YearInReview\YearInReviewListViewBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class YearInReviewListViewBuilderTest extends TestCase
{
    public function testOneCardPerStoredYearMostRecentFirst(): void
    {
        $user = $this->user('2025-11-01');
        $builder = $this->builder('2027-12-20 10:00:00', [
            YearInReview::eligible($user, 2027, $this->snapshot(workoutCount: 139, tonnageKg: 1292934.4)),
            YearInReview::notEligible($user, 2026, 3),
        ]);

        self::assertEquals([
            new YearInReviewCard(2027, YearInReviewCardStateEnum::READY, 139, 0, 1293, UnitOfMeasureEnum::KG, isUnseen: true),
            new YearInReviewCard(2026, YearInReviewCardStateEnum::NOT_ENOUGH, 3, 2, 0, UnitOfMeasureEnum::KG),
        ], $builder->cards($user));
    }

    public function testOpenedReviewIsNoLongerNew(): void
    {
        $user = $this->user('2025-11-01');
        $review = YearInReview::eligible($user, 2026, $this->snapshot(workoutCount: 5, tonnageKg: 1000.0));
        $review->seenAt = new \DateTimeImmutable('2026-12-16 08:00:00');

        self::assertFalse($this->builder('2026-12-20 10:00:00', [$review])->cards($user)[0]->isUnseen);
    }

    public function testTonnageFollowsTheReaderUnit(): void
    {
        $user = $this->user('2025-11-01');
        $user->unitOfMeasure = UnitOfMeasureEnum::LBS;
        $builder = $this->builder('2026-12-20 10:00:00', [YearInReview::eligible($user, 2026, $this->snapshot(workoutCount: 5, tonnageKg: 1000.0))]);

        self::assertSame(2205, $builder->cards($user)[0]->tonnage);
    }

    public function testYearStillBeingGeneratedForAnAccountThatExistedAtPublication(): void
    {
        $user = $this->user('2026-06-01');

        $cards = $this->builder('2026-12-16 00:02:00', [])->cards($user);

        self::assertEquals([new YearInReviewCard(2026, YearInReviewCardStateEnum::PENDING, 0, 0, 0, UnitOfMeasureEnum::KG)], $cards);
    }

    public function testNoCardForAYearPublishedBeforeRegistration(): void
    {
        $user = $this->user('2026-12-20');

        self::assertSame([], $this->builder('2027-03-01 10:00:00', [])->cards($user));
    }

    /**
     * @param list<YearInReview> $storedReviews
     */
    private function builder(string $now, array $storedReviews): YearInReviewListViewBuilder
    {
        $repository = $this->createStub(YearInReviewRepository::class);
        $repository->method('findByOwnerUpToYear')->willReturn($storedReviews);

        return new YearInReviewListViewBuilder(new YearInReviewCalendar(new MockClock(new \DateTimeImmutable($now . ' Europe/Paris'))), $repository);
    }

    private function user(string $registeredOn): User
    {
        $user = new User();
        $user->createdAt = new \DateTimeImmutable($registeredOn . ' 09:00:00');

        return $user;
    }

    private function snapshot(int $workoutCount, float $tonnageKg): YearInReviewSnapshot
    {
        return new YearInReviewSnapshot(
            new YearInReviewTotals($workoutCount, 0, 0, $tonnageKg),
            [],
            [],
            null,
            new YearInReviewRecords(0, null),
            new YearInReviewRegularity(1, 1, $workoutCount),
            [],
            null,
        );
    }
}
