<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Repository\ExerciseSetRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\Workout\WorkoutRecordDetectionService;
use App\Service\YearInReview\Snapshot\YearInReviewHeaviestSet;
use App\Service\YearInReview\Snapshot\YearInReviewRecords;

/**
 * Même définition de record que le dashboard (`WorkoutRecordDetectionService::findPrEvents()`),
 * restreinte à la période.
 */
final readonly class YearInReviewRecordsCalculator
{
    public function __construct(
        private WorkoutRecordDetectionService $workoutRecordDetectionService,
        private ExerciseSetRepository $exerciseSetRepository,
    ) {
    }

    public function calculate(User $user, DashboardPeriod $period): YearInReviewRecords
    {
        return new YearInReviewRecords($this->countRecords($user, $period), $this->heaviestSet($user, $period));
    }

    private function countRecords(User $user, DashboardPeriod $period): int
    {
        return \count(array_filter(
            $this->workoutRecordDetectionService->findPrEvents($user),
            static fn (array $event): bool => $period->start <= $event['performedAt'] && $event['performedAt'] <= $period->end,
        ));
    }

    private function heaviestSet(User $user, DashboardPeriod $period): ?YearInReviewHeaviestSet
    {
        $row = $this->exerciseSetRepository->findHeaviestSetInRange($user, $period->start, $period->end);

        if (null === $row) {
            return null;
        }

        return new YearInReviewHeaviestSet($row['exerciseName'], $row['isPublicExercise'], $row['weight'], $row['performedAt']->setTime(0, 0));
    }
}
