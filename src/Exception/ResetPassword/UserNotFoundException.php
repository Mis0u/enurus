<?php

declare(strict_types=1);

namespace App\Exception\ResetPassword;

use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;

class UserNotFoundException extends \Exception implements ResetPasswordExceptionInterface
{
    public function getReason(): string
    {
        return 'User not found for the reset password token';
    }
}
