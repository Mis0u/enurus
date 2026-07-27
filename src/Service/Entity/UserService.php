<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\User;
use App\Service\Email\EmailInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use function Symfony\Component\Clock\now;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class UserService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private EmailInterface $emailService,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    public function createUser(User $user, string $plainPassword, string $locale): User
    {
        $this->hashPassword($user, $plainPassword);
        $user->lastLogin = now();
        $user->locale = $locale;
        $this->save($user);

        return $user;
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function hashPassword(User $user, string $plainPassword): void
    {
        $user->password = $this->passwordHasher->hashPassword($user, $plainPassword);
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (! $this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return false;
        }

        $this->hashPassword($user, $newPassword);
        $this->save($user);
        $this->sendPasswordChangedEmail($user);

        return true;
    }

    /**
     * Envoyée en synchrone (hors file `async`) : une notification de sécurité doit arriver
     * immédiatement, sans dépendre du prochain passage d'un worker Messenger. Un échec d'envoi
     * (mailer indisponible) ne doit jamais faire échouer le changement de mot de passe lui-même
     * (déjà persisté à ce stade) — capturé et loggé plutôt que remonté à l'appelant.
     */
    private function sendPasswordChangedEmail(User $user): void
    {
        $email = $this->emailService->createEmail(
            $user->email,
            $this->translator->trans('settings.password_changed.subject', [], 'navigation', $user->locale),
            [
                'user' => $user,
                'locale' => $user->locale,
            ],
            'emails/password_changed.html.twig',
            $user->locale,
        );

        $email->getHeaders()->addTextHeader('X-Bus-Transport', 'sync');

        try {
            $this->emailService->sendEmail($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send password-changed email.', [
                'userId' => $user->id,
                'exception' => $e,
            ]);
        }
    }
}
