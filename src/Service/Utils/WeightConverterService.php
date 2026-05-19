<?php

declare(strict_types=1);

namespace App\Service\Utils;

use App\Enum\Entity\User\UnitOfMeasureEnum;

class WeightConverterService
{
    public function convertToLbs(float $weightKg, UnitOfMeasureEnum $unit): float
    {
        return match ($unit) {
            UnitOfMeasureEnum::KG => $weightKg,
            UnitOfMeasureEnum::LBS => round($weightKg * UnitOfMeasureEnum::WEIGHT_IN_LBS, 1),
        };
    }

    public function convertToKg(float $weight, UnitOfMeasureEnum $unit): float
    {
        return match ($unit) {
            UnitOfMeasureEnum::KG => $weight,
            UnitOfMeasureEnum::LBS => round($weight / UnitOfMeasureEnum::WEIGHT_IN_LBS, 2),
        };
    }

    public function format(float $weightKg, UnitOfMeasureEnum $unit): string
    {
        $weight = $this->convertToLbs($weightKg, $unit);
        return \sprintf('%s %s', $weight, $unit->label());
    }
}
