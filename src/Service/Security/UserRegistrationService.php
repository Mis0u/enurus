<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use App\Service\Email\EmailInterface;
use App\Service\Entity\UserService;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class UserRegistrationService
{
    public function __construct(
        private UserService $userService,
        private EmailInterface $emailService,
        private TranslatorInterface $translator
    ) {
    }

    public function registerUser(User $user, string $plainPassword, string $locale): User
    {
        $this->userService->createUser($user, $plainPassword, $locale);
        $this->sendRegistrationEmail($user, $locale);

        return $user;
    }

    private function sendRegistrationEmail(User $user, string $locale): void
    {
        $mail = $this->emailService->createEmail(
            (string) $user->getEmail(),
            $this->translator->trans('registration.email.welcome', [
                'brand' => $this->translator->trans('name', [], 'brand'),
            ], 'security'),
            [
                'user' => $user,
                'locale' => $locale,
            ],
            'registration/email/welcome.html.twig',
            $locale
        );

        $this->emailService->sendEmail($mail);
    }
}
