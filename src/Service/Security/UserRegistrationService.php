<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use App\Service\Entity\UserService;

readonly class UserRegistrationService
{
    public function __construct(
        private UserService $userService,
    ) {
    }

    public function registerUser(User $user, string $plainPassword, string $locale): User
    {
        $this->userService->createUser($user, $plainPassword, $locale);

        return $user;
    }
}
