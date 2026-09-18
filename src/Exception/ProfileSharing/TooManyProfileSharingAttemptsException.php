<?php

declare(strict_types=1);

namespace App\Exception\ProfileSharing;

final class TooManyProfileSharingAttemptsException extends \RuntimeException
{
    public function __construct(
        public readonly int $retryAfterMinutes
    ) {
        parent::__construct(\sprintf('Too many attempts, retry in %d minute(s).', $retryAfterMinutes));
    }
}
