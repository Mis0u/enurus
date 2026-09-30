<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Enum\Entity\Workout\WorkoutMoodEnum;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\YearInReview\Snapshot\YearInReviewMood;

final readonly class YearInReviewMoodCalculator
{
    private const int PERCENT = 100;

    public function __construct(
        private WorkoutStatsRepository $workoutStatsRepository,
    ) {
    }

    /**
     * Null si aucune humeur saisie : l'écran Humeur est alors masqué. En cas d'égalité, l'ordre de
     * `WorkoutMoodEnum` départage.
     */
    public function calculate(User $user, DashboardPeriod $period): ?YearInReviewMood
    {
        $countByMood = $this->workoutStatsRepository->countMoodsInRange($user, $period->start, $period->end);
        $dominantMood = null;
        $dominantCount = 0;

        foreach (WorkoutMoodEnum::cases() as $mood) {
            if (($countByMood[$mood->value] ?? 0) > $dominantCount) {
                $dominantMood = $mood;
                $dominantCount = $countByMood[$mood->value];
            }
        }

        if (null === $dominantMood) {
            return null;
        }

        return new YearInReviewMood($dominantMood, (int) round($dominantCount * self::PERCENT / array_sum($countByMood)));
    }
}
