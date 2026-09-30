<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\YearInReview\YearInReviewScreenEnum;

final readonly class RepsScreen implements YearInReviewScreen
{
    public function __construct(
        public int $repCount,
        public int $setCount,
    ) {
    }

    public function type(): YearInReviewScreenEnum
    {
        return YearInReviewScreenEnum::REPS;
    }
}
