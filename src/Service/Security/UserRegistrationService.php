<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use App\Service\Contact\RegistrationWelcomeThreadService;
use App\Service\Email\EmailInterface;
use App\Service\Entity\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class UserRegistrationService
{
    public function __construct(
        private UserService $userService,
        private EmailVerificationService $emailVerificationService,
        private EmailInterface $emailService,
        private TranslatorInterface $translator,
        private RegistrationWelcomeThreadService $welcomeThreadService,
        private DeletedAccountReregistrationNotifierService $reregistrationNotifier,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Crée le compte (`isVerified = false`, TODO #24) et envoie l'email de confirmation. Le reste
     * de l'accueil (email de bienvenue, fil de bienvenue, détection de réinscription, connexion
     * auto) n'a lieu qu'au clic sur le lien de confirmation, cf. `completeRegistration()`.
     */
    public function registerUser(User $user, string $plainPassword, string $locale): User
    {
        $this->userService->createUser($user, $plainPassword, $locale);
        $this->emailVerificationService->sendConfirmationEmail($user, $locale);

        return $user;
    }

    /**
     * Déclenché depuis `EmailVerificationController` une fois `User::$isVerified` passé à `true`.
     */
    public function completeRegistration(User $user, string $locale): void
    {
        $this->sendWelcomeEmail($user, $locale);
        $this->welcomeThreadService->create($user, $locale);
        $this->reregistrationNotifier->notifyIfReregistration($user);
    }

    /**
     * Envoyée en synchrone (hors file `async`) : l'utilisateur attend cet email juste après avoir
     * confirmé son adresse. Un échec d'envoi (mailer indisponible) ne doit jamais faire échouer la
     * confirmation elle-même (compte déjà vérifié à ce stade) — capturé et loggé plutôt que remonté
     * à l'appelant.
     */
    private function sendWelcomeEmail(User $user, string $locale): void
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

        $mail->getHeaders()->addTextHeader('X-Bus-Transport', 'sync');

        try {
            $this->emailService->sendEmail($mail);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send registration welcome email.', [
                'userId' => $user->id,
                'exception' => $e,
            ]);
        }
    }
}
