<?php

declare(strict_types=1);

namespace App\Enum\Contact;

enum ContactBroadcastTargetEnum: string
{
    case ALL = 'all';
    case LOCALE = 'locale';
}
