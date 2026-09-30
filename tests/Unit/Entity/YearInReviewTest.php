<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\YearInReview;
use App\Service\YearInReview\Snapshot\YearInReviewRecords;
use App\Service\YearInReview\Snapshot\YearInReviewRegularity;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;
use PHPUnit\Framework\TestCase;

final class YearInReviewTest extends TestCase
{
    public function testReviewWithoutSnapshotIsNotEligible(): void
    {
        $review = YearInReview::notEligible(new User(), 2026, workoutCount: 3);

        self::assertFalse($review->isEligible());
        self::assertNull($review->snapshot());
        self::assertSame(2, $review->missingWorkoutCount());
    }

    public function testReviewWithSnapshotIsEligible(): void
    {
        $snapshot = $this->snapshot(workoutCount: 5);

        $review = YearInReview::eligible(new User(), 2026, $snapshot);

        self::assertTrue($review->isEligible());
        self::assertSame(5, $review->workoutCount);
        self::assertSame(0, $review->missingWorkoutCount());
        self::assertEquals($snapshot, $review->snapshot());
    }

    private function snapshot(int $workoutCount): YearInReviewSnapshot
    {
        return new YearInReviewSnapshot(
            new YearInReviewTotals($workoutCount, 20, 180, 5400.0),
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
