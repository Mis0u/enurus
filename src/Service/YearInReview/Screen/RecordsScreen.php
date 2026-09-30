<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\YearInReview\YearInReviewScreenEnum;

final readonly class RecordsScreen implements YearInReviewScreen
{
    public function __construct(
        public int $count,
        public ?HeaviestSetView $heaviestSet,
    ) {
    }

    public function type(): YearInReviewScreenEnum
    {
        return YearInReviewScreenEnum::RECORDS;
    }
}
