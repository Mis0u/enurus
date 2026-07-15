<?php

declare(strict_types=1);

namespace App\Enum\Contact;

enum ContactThreadStatusEnum: string
{
    case AWAITING_ADMIN_REPLY = 'awaiting_admin_reply';
    case ANSWERED_BY_ADMIN = 'answered_by_admin';
    case CLOSED = 'closed';
}
