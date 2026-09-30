<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\YearInReview\YearInReviewScreenEnum;
use App\Service\YearInReview\Snapshot\YearInReviewExercise;

final readonly class TopExercisesScreen implements YearInReviewScreen
{
    public function __construct(
        /**
         * @var list<YearInReviewExercise>
         */
        public array $exercises,
    ) {
    }

    public function type(): YearInReviewScreenEnum
    {
        return YearInReviewScreenEnum::TOP_EXERCISES;
    }
}
