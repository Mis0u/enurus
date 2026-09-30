<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\YearInReview\YearInReviewScreenEnum;

final readonly class SessionsScreen implements YearInReviewScreen
{
    public function __construct(
        public int $workoutCount,
        public float $averagePerWeek,
    ) {
    }

    public function type(): YearInReviewScreenEnum
    {
        return YearInReviewScreenEnum::SESSIONS;
    }
}
