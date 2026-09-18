<?php

declare(strict_types=1);

namespace App\Enum\Entity\ProfileConnection;

enum ProfileConnectionStatusEnum: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
    case REVOKED = 'revoked';
}
