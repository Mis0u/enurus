<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\YearInReview\YearInReviewScreenEnum;

/**
 * `equivalence` reste null sous le plus léger objet de l'échelle (le tonnage s'affiche seul).
 */
final readonly class TonnageScreen implements YearInReviewScreen
{
    public function __construct(
        public YearInReviewWeight $tonnage,
        public ?TonnageEquivalence $equivalence,
    ) {
    }

    public function type(): YearInReviewScreenEnum
    {
        return YearInReviewScreenEnum::TONNAGE;
    }
}
