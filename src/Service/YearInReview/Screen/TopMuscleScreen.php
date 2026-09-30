<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\Entity\User\GenderEnum;
use App\Enum\YearInReview\YearInReviewScreenEnum;

/**
 * `muscleName` : clé du domaine de traduction `muscle` ; `svgIds` coloriés sur la silhouette du lecteur.
 */
final readonly class TopMuscleScreen implements YearInReviewScreen
{
    public function __construct(
        public string $muscleName,
        public int $setCount,
        /**
         * @var list<string>
         */
        public array $svgIds,
        public GenderEnum $gender,
    ) {
    }

    public function type(): YearInReviewScreenEnum
    {
        return YearInReviewScreenEnum::TOP_MUSCLE;
    }
}
