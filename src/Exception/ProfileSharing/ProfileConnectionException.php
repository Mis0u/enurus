<?php

declare(strict_types=1);

namespace App\Exception\ProfileSharing;

final class ProfileConnectionException extends \RuntimeException
{
    public function __construct(
        public readonly ProfileConnectionFailureReasonEnum $reason
    ) {
        parent::__construct($reason->value);
    }
}
