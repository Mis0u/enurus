<?php

declare(strict_types=1);

namespace App\Service\YearInReview;

use App\Entity\User;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\YearInReview\Calculator\YearInReviewBadgesCalculator;
use App\Service\YearInReview\Calculator\YearInReviewHeatmapCalculator;
use App\Service\YearInReview\Calculator\YearInReviewMoodCalculator;
use App\Service\YearInReview\Calculator\YearInReviewRecordsCalculator;
use App\Service\YearInReview\Calculator\YearInReviewRegularityCalculator;
use App\Service\YearInReview\Calculator\YearInReviewTopExercisesCalculator;
use App\Service\YearInReview\Calculator\YearInReviewTopMuscleCalculator;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;

/**
 * Assemble les écrans d'un résumé éligible, un calculateur par écran.
 */
final readonly class YearInReviewSnapshotBuilder
{
    public function __construct(
        private YearInReviewHeatmapCalculator $heatmapCalculator,
        private YearInReviewTopExercisesCalculator $topExercisesCalculator,
        private YearInReviewTopMuscleCalculator $topMuscleCalculator,
        private YearInReviewRecordsCalculator $recordsCalculator,
        private YearInReviewRegularityCalculator $regularityCalculator,
        private YearInReviewBadgesCalculator $badgesCalculator,
        private YearInReviewMoodCalculator $moodCalculator,
    ) {
    }

    public function build(User $user, DashboardPeriod $period, YearInReviewTotals $totals): YearInReviewSnapshot
    {
        return new YearInReviewSnapshot(
            $totals,
            $this->heatmapCalculator->calculate($user, $period),
            $this->topExercisesCalculator->calculate($user, $period),
            $this->topMuscleCalculator->calculate($user, $period),
            $this->recordsCalculator->calculate($user, $period),
            $this->regularityCalculator->calculate($user, $period),
            $this->badgesCalculator->calculate($user, $period),
            $this->moodCalculator->calculate($user, $period),
        );
    }
}
