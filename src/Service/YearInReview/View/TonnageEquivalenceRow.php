<?php

declare(strict_types=1);

namespace App\Service\YearInReview\View;

use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\YearInReview\TonnageEquivalenceEnum;

/**
 * Une ligne de l'échelle des équivalences (bloc admin), poids déjà dans l'unité du lecteur.
 */
final readonly class TonnageEquivalenceRow
{
    public function __construct(
        public TonnageEquivalenceEnum $object,
        public int $weight,
        public UnitOfMeasureEnum $unit,
    ) {
    }
}
