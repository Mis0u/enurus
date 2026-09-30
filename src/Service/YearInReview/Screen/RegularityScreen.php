<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\YearInReview\YearInReviewScreenEnum;

/**
 * `busiestMonth` : premier jour du mois le plus chargé (formaté selon la langue à l'affichage).
 */
final readonly class RegularityScreen implements YearInReviewScreen
{
    public function __construct(
        public int $longestStreakWeeks,
        public \DateTimeImmutable $busiestMonth,
        public int $busiestMonthWorkoutCount,
    ) {
    }

    public function type(): YearInReviewScreenEnum
    {
        return YearInReviewScreenEnum::REGULARITY;
    }
}
