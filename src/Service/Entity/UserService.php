<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\User;
use App\Service\Email\EmailInterface;
use Doctrine\ORM\EntityManagerInterface;
use function Symfony\Component\Clock\now;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class UserService
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private EmailInterface $emailService,
        private TranslatorInterface $translator,
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
     * immédiatement, sans dépendre du prochain passage d'un worker Messenger.
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

        $this->emailService->sendEmail($email);
    }
}
