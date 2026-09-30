<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\YearInReview\YearInReviewScreenEnum;

final readonly class IntroScreen implements YearInReviewScreen
{
    public function __construct(
        public int $year,
    ) {
    }

    public function type(): YearInReviewScreenEnum
    {
        return YearInReviewScreenEnum::INTRO;
    }
}
