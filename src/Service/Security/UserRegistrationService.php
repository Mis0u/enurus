<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use App\Service\Contact\RegistrationWelcomeThreadService;
use App\Service\Email\EmailInterface;
use App\Service\Entity\UserService;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class UserRegistrationService
{
    public function __construct(
        private UserService $userService,
        private EmailInterface $emailService,
        private TranslatorInterface $translator,
        private RegistrationWelcomeThreadService $welcomeThreadService,
        private DeletedAccountReregistrationNotifierService $reregistrationNotifier,
    ) {
    }

    public function registerUser(User $user, string $plainPassword, string $locale): User
    {
        $this->userService->createUser($user, $plainPassword, $locale);
        $this->sendRegistrationEmail($user, $locale);
        $this->welcomeThreadService->create($user, $locale);
        $this->reregistrationNotifier->notifyIfReregistration($user);

        return $user;
    }

    private function sendRegistrationEmail(User $user, string $locale): void
    {
        $mail = $this->emailService->createEmail(
            $user->email,
            $this->translator->trans('registration.email.welcome', [
                'brand' => $this->translator->trans('name', [], 'brand'),
            ], 'navigation'),
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
