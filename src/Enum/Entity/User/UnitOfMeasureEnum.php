<?php

declare(strict_types=1);

namespace App\Enum\Entity\User;

enum UnitOfMeasureEnum: string
{
    case KG = 'kg';
    case LBS = 'lbs';

    public const float WEIGHT_IN_KG = 1.0;

    public const float WEIGHT_IN_LBS = 2.20462;

    public function label(): string
    {
        return match ($this) {
            self::KG => self::KG->value,
            self::LBS => self::LBS->value,
        };
    }

    public function factor(): float
    {
        return match ($this) {
            self::KG => self::WEIGHT_IN_KG,
            self::LBS => self::WEIGHT_IN_LBS,
        };
    }
}
