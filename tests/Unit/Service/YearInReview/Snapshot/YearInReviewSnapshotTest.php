<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview\Snapshot;

use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Enum\Entity\Workout\WorkoutMoodEnum;
use App\Service\YearInReview\Snapshot\YearInReviewBadge;
use App\Service\YearInReview\Snapshot\YearInReviewExercise;
use App\Service\YearInReview\Snapshot\YearInReviewHeaviestSet;
use App\Service\YearInReview\Snapshot\YearInReviewMood;
use App\Service\YearInReview\Snapshot\YearInReviewMuscle;
use App\Service\YearInReview\Snapshot\YearInReviewRecords;
use App\Service\YearInReview\Snapshot\YearInReviewRegularity;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;
use PHPUnit\Framework\TestCase;

final class YearInReviewSnapshotTest extends TestCase
{
    public function testFullSnapshotSurvivesJsonRoundTrip(): void
    {
        $snapshot = new YearInReviewSnapshot(
            new YearInReviewTotals(workoutCount: 142, setCount: 2480, repCount: 21350, tonnageKg: 312450.5),
            [
                '2026-01-05' => 4200.0,
                '2026-01-07' => 3875.5,
            ],
            [new YearInReviewExercise('exercise.bench_press', true, 96), new YearInReviewExercise('Mon squat perso', false, 71)],
            new YearInReviewMuscle('0199aaaa-0000-7000-8000-000000000001', 612),
            new YearInReviewRecords(37, new YearInReviewHeaviestSet('exercise.deadlift', true, 180.0, new \DateTimeImmutable('2026-11-14'))),
            new YearInReviewRegularity(longestStreakWeeks: 14, busiestMonth: 3, busiestMonthWorkoutCount: 17),
            [new YearInReviewBadge(BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::GOLD)],
            new YearInReviewMood(WorkoutMoodEnum::EN_FORME, 46),
        );

        self::assertEquals($snapshot, YearInReviewSnapshot::fromArray($this->jsonRoundTrip($snapshot->toArray())));
    }

    public function testEmptySectionsSurviveJsonRoundTrip(): void
    {
        $snapshot = new YearInReviewSnapshot(
            new YearInReviewTotals(workoutCount: 5, setCount: 0, repCount: 0, tonnageKg: 0.0),
            [],
            [],
            null,
            new YearInReviewRecords(0, null),
            new YearInReviewRegularity(longestStreakWeeks: 1, busiestMonth: 6, busiestMonthWorkoutCount: 5),
            [],
            null,
        );

        self::assertEquals($snapshot, YearInReviewSnapshot::fromArray($this->jsonRoundTrip($snapshot->toArray())));
    }

    public function testMalformedDataIsRejected(): void
    {
        $this->expectException(\LogicException::class);

        YearInReviewSnapshot::fromArray([
            'totals' => 'not an array',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<mixed>
     */
    private function jsonRoundTrip(array $data): array
    {
        $decoded = json_decode(json_encode($data, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
