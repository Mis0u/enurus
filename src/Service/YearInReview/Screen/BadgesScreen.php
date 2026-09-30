<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\YearInReview\YearInReviewScreenEnum;
use App\Service\Badge\View\BadgeTileView;

final readonly class BadgesScreen implements YearInReviewScreen
{
    public function __construct(
        /**
         * @var list<BadgeTileView>
         */
        public array $badges,
    ) {
    }

    public function type(): YearInReviewScreenEnum
    {
        return YearInReviewScreenEnum::BADGES;
    }
}
