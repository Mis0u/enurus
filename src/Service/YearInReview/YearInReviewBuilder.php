<?php

declare(strict_types=1);

namespace App\Service\YearInReview;

use App\Entity\User;
use App\Entity\YearInReview;
use App\Service\YearInReview\Calculator\YearInReviewTotalsCalculator;

/**
 * Calcule le résumé figé d'une année : sans snapshot sous le seuil de séances (message
 * d'encouragement), complet sinon. Ne persiste rien.
 */
final readonly class YearInReviewBuilder
{
    public function __construct(
        private YearInReviewCalendar $calendar,
        private YearInReviewTotalsCalculator $totalsCalculator,
        private YearInReviewSnapshotBuilder $snapshotBuilder,
    ) {
    }

    public function build(User $user, int $year): YearInReview
    {
        $period = $this->calendar->periodOf($year);
        $totals = $this->totalsCalculator->calculate($user, $period);

        if (YearInReview::MINIMUM_WORKOUT_COUNT > $totals->workoutCount) {
            return YearInReview::notEligible($user, $year, $totals->workoutCount);
        }

        return YearInReview::eligible($user, $year, $this->snapshotBuilder->build($user, $period, $totals));
    }
}
