<?php

declare(strict_types=1);

namespace App\Enum\Password;

enum PasswordRuleEnum: string
{
    case MIN_LENGTH = '12';
    case SPECIAL_CHARS = '!?%$*&#@_-';
    case REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!?%$*&#@_-]).{12,}$/';
}
