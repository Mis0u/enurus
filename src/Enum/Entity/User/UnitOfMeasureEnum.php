<?php

declare(strict_types=1);

namespace App\Enum\Entity\User;

enum UnitOfMeasureEnum: string
{
    case KG = 'kg';
    case LBS = 'lbs';

    public const float WEIGHT_IN_LBS = 2.20462;
}
