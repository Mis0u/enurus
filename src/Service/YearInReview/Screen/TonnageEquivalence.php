<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\YearInReview\TonnageEquivalenceEnum;

/**
 * « C'est plus que le poids de {count} {object} », avec le poids de référence affiché sous l'objet.
 */
final readonly class TonnageEquivalence
{
    public function __construct(
        public TonnageEquivalenceEnum $object,
        public int $count,
        public YearInReviewWeight $referenceWeight,
    ) {
    }
}
