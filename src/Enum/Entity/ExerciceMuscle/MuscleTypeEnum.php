<?php

declare(strict_types=1);

namespace App\Enum\Entity\ExerciceMuscle;

enum MuscleTypeEnum: string
{
    case PRIMARY = 'primary';
    case SECONDARY = 'secondary';
}
