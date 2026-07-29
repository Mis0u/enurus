<?php

declare(strict_types=1);

namespace App\Service\Translation;

use App\Entity\ContactBroadcast;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Exception\Translation\TranslationFailedException;
use App\Repository\UserRepository;
use App\Service\Email\EmailInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Alerte l'admin par un vrai email (pas un ContactThread interne) quand une traduction DeepL échoue
 * pendant l'envoi d'une diffusion "tous les utilisateurs" — ne doit jamais lancer d'exception : un
 * échec de notification est un effet de bord qui ne doit pas empêcher la propagation de
 * TranslationFailedException vers Messenger (seul mécanisme de retry pour la diffusion elle-même).
 */
final readonly class BroadcastTranslationFailureNotifierService
{
    public function __construct(
        private EmailInterface $emailService,
        private UserRepository $userRepository,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private string $adminUserEmail,
    ) {
    }

    public function notify(ContactBroadcast $broadcast, LocaleAllowedEnum $targetLocale, TranslationFailedException $exception): void
    {
        $admin = $this->userRepository->findOneByEmail($this->adminUserEmail);

        if (null === $admin) {
            $this->logger->error('Cannot notify admin of broadcast translation failure: admin account not found.', [
                'adminUserEmail' => $this->adminUserEmail,
                'exception' => $exception,
            ]);

            return;
        }

        $email = $this->emailService->createEmail(
            $admin->email,
            $this->translator->trans('admin.broadcast_translation_failed.subject', [], 'navigation', $admin->locale),
            [
                'broadcastSubject' => $broadcast->subject,
                'targetLocale' => $targetLocale->value,
                'reason' => $exception->getMessage(),
                'locale' => $admin->locale,
            ],
            'emails/broadcast_translation_failed.html.twig',
            $admin->locale,
        );

        $email->getHeaders()->addTextHeader('X-Bus-Transport', 'sync');

        try {
            $this->emailService->sendEmail($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send broadcast translation failure email.', [
                'exception' => $e,
            ]);
        }
    }
}
