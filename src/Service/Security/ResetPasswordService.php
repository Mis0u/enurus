<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use App\Exception\ResetPassword\UserNotFoundException;
use App\Service\Email\SymfonyMailerEmailService;
use App\Service\Entity\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\InvalidResetPasswordTokenException;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

final readonly class ResetPasswordService
{
    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
        private UserService $userService,
        private SymfonyMailerEmailService $emailService,
        private TranslatorInterface $translator,
    ) {
    }

    public function resetPassword(string $token, string $plainPassword, User $user): void
    {
        // A password reset token should be used only once, remove it.
        $this->resetPasswordHelper->removeResetRequest($token);

        $this->userService->hashPassword($user, $plainPassword);
        $this->entityManager->flush();
    }

    public function validateToken(string $token): User
    {
        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
            return $user;
        } catch (ResetPasswordExceptionInterface $e) {
            throw new InvalidResetPasswordTokenException($e->getReason());
        }
    }

    public function resolveResetToken(?ResetPasswordToken $tokenObjectFromSession): ResetPasswordToken
    {
        return $tokenObjectFromSession ?? $this->resetPasswordHelper->generateFakeResetToken();
    }

    public function processSendingPasswordResetEmail(
        string $emailFormData,
        string $locale
    ): ResetPasswordToken {
        $user = $this->findUserByEmail($emailFormData);

        if (null === $user) {
            throw new UserNotFoundException();
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            throw new InvalidResetPasswordTokenException($e->getReason());
        }

        $this->sendResetPasswordEmail(
            $user,
            $resetToken,
            $locale,
        );

        return $resetToken;

    }

    private function findUserByEmail(string $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $email,
        ]);
    }

    private function sendResetPasswordEmail(
        User $user,
        ResetPasswordToken $resetToken,
        string $locale,
    ): void {
        $email = $this->emailService->createEmail(
            $user->email,
            $this->translator->trans('reset_password_request.reseting_password', [], 'security', $locale),
            [
                'locale' => $locale,
                'resetToken' => $resetToken,
            ],
            'reset_password/email.html.twig',
            $locale
        );

        $this->emailService->sendEmail($email);
    }
}
