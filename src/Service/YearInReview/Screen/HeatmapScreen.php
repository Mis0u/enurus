<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\YearInReview\YearInReviewScreenEnum;

/**
 * @phpstan-import-type HeatmapData from \App\Service\Workout\WorkoutHeatmapService
 */
final readonly class HeatmapScreen implements YearInReviewScreen
{
    public function __construct(
        public int $trainingDayCount,
        /**
         * @var HeatmapData
         */
        public array $heatmapData,
    ) {
    }

    public function type(): YearInReviewScreenEnum
    {
        return YearInReviewScreenEnum::HEATMAP;
    }
}
