<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use App\Security\EmailVerifier;
use App\Service\Email\EmailService;
use App\Service\Entity\UserService;

readonly class UserRegistrationService
{
    public function __construct(
        private UserService $userService,
        private EmailService $emailService,
        private EmailVerifier $emailVerifier,
    ) {
    }

    public function registerUser(User $user, string $plainPassword, string $locale): User
    {
        $this->userService->createUser($user, $plainPassword, $locale);

        $this->sendConfirmationEmail($user, $locale);

        return $user;
    }

    private function sendConfirmationEmail(User $user, string $locale): void
    {
        $this->emailVerifier->sendEmailConfirmation(
            'app_verify_email',
            $user,
            $this->emailService->createRegistrationConfirmationEmail($user, $locale)
        );
    }
}
