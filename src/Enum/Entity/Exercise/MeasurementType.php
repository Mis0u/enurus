<?php

declare(strict_types=1);

namespace App\Enum\Entity\Exercise;

enum MeasurementType: string
{
    case WEIGHT_REPS = 'weight_reps';
    case TIME = 'time';
}
