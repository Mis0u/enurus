<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use App\Service\Email\EmailInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelper;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final readonly class EmailVerificationService
{
    public function __construct(
        /**
         * `validateEmailConfirmationFromRequest()` n'est pas déclarée sur `VerifyEmailHelperInterface`
         * (seulement en `@method` sur la classe concrète, pour compat BC) — on type-hint la classe
         * concrète mais on force l'autowiring via l'alias public de l'interface plutôt que d'utiliser
         * `validateEmailConfirmation()` (dépréciée depuis 1.17).
         */
        #[Autowire(service: VerifyEmailHelperInterface::class)]
        private VerifyEmailHelper $verifyEmailHelper,
        private EntityManagerInterface $entityManager,
        private EmailInterface $emailService,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Envoyée en synchrone : l'utilisateur attend cet email juste après son inscription. Un échec
     * d'envoi (mailer indisponible) ne doit jamais faire échouer l'inscription elle-même (compte
     * déjà créé à ce stade) — capturé et loggé plutôt que remonté à l'appelant.
     */
    public function sendConfirmationEmail(User $user, string $locale): void
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            'app_verify_email',
            (string) $user->id,
            $user->email,
            [
                '_locale' => $locale,
                'id' => (string) $user->id,
            ],
        );

        $mail = $this->emailService->createEmail(
            $user->email,
            $this->translator->trans('registration.email.registration_confirm', [
                'brand' => $this->translator->trans('name', [], 'brand'),
            ], 'navigation', $locale),
            [
                'user' => $user,
                'locale' => $locale,
                'signature' => $signature,
            ],
            'registration/email/confirm_email.html.twig',
            $locale
        );

        $mail->getHeaders()->addTextHeader('X-Bus-Transport', 'sync');

        try {
            $this->emailService->sendEmail($mail);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send email confirmation.', [
                'userId' => $user->id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * @throws VerifyEmailExceptionInterface
     */
    public function confirmEmail(Request $request, User $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            $request,
            (string) $user->id,
            $user->email,
        );

        $user->isVerified = true;
        $this->entityManager->flush();
    }
}
