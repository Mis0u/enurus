<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\YearInReview\YearInReviewWeightUnitEnum;

/**
 * Un poids du résumé annuel converti depuis les kg du snapshot, dans l'unité et l'échelle qui se
 * lisent le mieux : tonnes entières pour le tonnage de l'année, kg ou tonnes pour un poids de
 * référence, poids au dixième pour une série. Les lecteurs en livres restent toujours en livres.
 */
final readonly class YearInReviewWeight
{
    private const float KG_PER_TONNE = 1000.0;

    public function __construct(
        public float $value,
        public YearInReviewWeightUnitEnum $unit,
    ) {
    }

    public static function tonnage(float $weightKg, UnitOfMeasureEnum $readerUnit): self
    {
        if (UnitOfMeasureEnum::LBS === $readerUnit) {
            return new self(round($weightKg * UnitOfMeasureEnum::WEIGHT_IN_LBS), YearInReviewWeightUnitEnum::POUND);
        }

        return new self(round($weightKg / self::KG_PER_TONNE), YearInReviewWeightUnitEnum::TONNE);
    }

    public static function reference(float $weightKg, UnitOfMeasureEnum $readerUnit): self
    {
        if (UnitOfMeasureEnum::LBS === $readerUnit) {
            return new self(round($weightKg * UnitOfMeasureEnum::WEIGHT_IN_LBS), YearInReviewWeightUnitEnum::POUND);
        }

        if (self::KG_PER_TONNE > $weightKg) {
            return new self($weightKg, YearInReviewWeightUnitEnum::KILOGRAM);
        }

        return new self(round($weightKg / self::KG_PER_TONNE, 1), YearInReviewWeightUnitEnum::TONNE);
    }

    public static function lifted(float $weightKg, UnitOfMeasureEnum $readerUnit): self
    {
        if (UnitOfMeasureEnum::LBS === $readerUnit) {
            return new self(round($weightKg * UnitOfMeasureEnum::WEIGHT_IN_LBS, 1), YearInReviewWeightUnitEnum::POUND);
        }

        return new self(round($weightKg, 1), YearInReviewWeightUnitEnum::KILOGRAM);
    }
}
