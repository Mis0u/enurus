<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Repository\ExerciseSetRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\Workout\WorkoutRecordDetectionService;
use App\Service\YearInReview\Calculator\YearInReviewRecordsCalculator;
use App\Service\YearInReview\Snapshot\YearInReviewHeaviestSet;
use App\Service\YearInReview\Snapshot\YearInReviewRecords;
use PHPUnit\Framework\TestCase;

final class YearInReviewRecordsCalculatorTest extends TestCase
{
    public function testCountsOnlyRecordsBeatenDuringThePeriod(): void
    {
        $calculator = $this->calculator([
            [
                'workoutId' => 'a',
                'performedAt' => new \DateTimeImmutable('2025-12-31 18:00:00'),
            ],
            [
                'workoutId' => 'b',
                'performedAt' => new \DateTimeImmutable('2026-01-01 00:00:00'),
            ],
            [
                'workoutId' => 'c',
                'performedAt' => new \DateTimeImmutable('2026-12-15 23:59:00'),
            ],
            [
                'workoutId' => 'd',
                'performedAt' => new \DateTimeImmutable('2026-12-16 08:00:00'),
            ],
        ], heaviestSet: null);

        self::assertEquals(new YearInReviewRecords(2, null), $calculator->calculate(new User(), $this->period()));
    }

    public function testKeepsTheHeaviestSetOfThePeriod(): void
    {
        $calculator = $this->calculator([], heaviestSet: [
            'exerciseName' => 'exercise.deadlift',
            'isPublicExercise' => true,
            'weight' => 180.0,
            'performedAt' => new \DateTimeImmutable('2026-11-14 18:30:00'),
        ]);

        $records = $calculator->calculate(new User(), $this->period());

        self::assertEquals(
            new YearInReviewHeaviestSet('exercise.deadlift', true, 180.0, new \DateTimeImmutable('2026-11-14 00:00:00')),
            $records->heaviestSet,
        );
    }

    /**
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable}>                                          $prEvents
     * @param array{exerciseName: string, isPublicExercise: bool, weight: float, performedAt: \DateTimeImmutable}|null $heaviestSet
     */
    private function calculator(array $prEvents, ?array $heaviestSet): YearInReviewRecordsCalculator
    {
        $recordDetection = $this->createStub(WorkoutRecordDetectionService::class);
        $recordDetection->method('findPrEvents')->willReturn($prEvents);
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findHeaviestSetInRange')->willReturn($heaviestSet);

        return new YearInReviewRecordsCalculator($recordDetection, $exerciseSetRepository);
    }

    private function period(): DashboardPeriod
    {
        return new DashboardPeriod(new \DateTimeImmutable('2026-01-01 00:00:00'), new \DateTimeImmutable('2026-12-15 23:59:59'));
    }
}
