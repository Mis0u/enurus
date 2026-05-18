<?php

declare(strict_types=1);

namespace App\Enum\Entity\User;

enum GenderEnum: string
{
    case MALE = 'male';
    case FEMALE = 'female';
}
