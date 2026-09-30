<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\Entity\Workout\WorkoutMoodEnum;
use App\Enum\YearInReview\YearInReviewScreenEnum;

final readonly class MoodScreen implements YearInReviewScreen
{
    public function __construct(
        public WorkoutMoodEnum $mood,
        public int $percent,
    ) {
    }

    public function type(): YearInReviewScreenEnum
    {
        return YearInReviewScreenEnum::MOOD;
    }
}
