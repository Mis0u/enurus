<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\Entity\Workout\WorkoutMoodEnum;
use App\Enum\YearInReview\YearInReviewScreenEnum;
use App\Service\YearInReview\Snapshot\YearInReviewExercise;

/**
 * « En bref » : une valeur par écran affiché, null quand son écran est masqué (tuile ou ligne absente).
 */
final readonly class RecapScreen implements YearInReviewScreen
{
    public function __construct(
        public int $workoutCount,
        public ?YearInReviewWeight $tonnage,
        public int $repCount,
        public ?int $recordCount,
        public int $longestStreakWeeks,
        public ?int $badgeCount,
        public ?YearInReviewExercise $topExercise,
        public ?string $topMuscleName,
        public ?WorkoutMoodEnum $mood,
    ) {
    }

    public function type(): YearInReviewScreenEnum
    {
        return YearInReviewScreenEnum::RECAP;
    }
}
