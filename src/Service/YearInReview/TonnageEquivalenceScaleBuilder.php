<?php

declare(strict_types=1);

namespace App\Service\YearInReview;

use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\YearInReview\TonnageEquivalenceEnum;
use App\Service\YearInReview\View\TonnageEquivalenceRow;

/**
 * Échelle des équivalences affichée à l'admin sur « Mes résumés », pour vérifier les poids de
 * référence et la règle « au moins une fois ».
 */
final readonly class TonnageEquivalenceScaleBuilder
{
    /**
     * @return list<TonnageEquivalenceRow>
     */
    public function rows(UnitOfMeasureEnum $unit): array
    {
        return array_map(
            static fn (TonnageEquivalenceEnum $object): TonnageEquivalenceRow => new TonnageEquivalenceRow($object, self::weightIn($object->weightKg(), $unit), $unit),
            TonnageEquivalenceEnum::cases(),
        );
    }

    private static function weightIn(float $weightKg, UnitOfMeasureEnum $unit): int
    {
        return (int) round(UnitOfMeasureEnum::LBS === $unit ? $weightKg * UnitOfMeasureEnum::WEIGHT_IN_LBS : $weightKg);
    }
}
