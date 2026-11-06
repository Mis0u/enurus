<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\ContactThread;
use App\Entity\User;
use App\Service\Email\EmailInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Prévient le destinataire d'un nouveau message de l'admin (diffusion, sondage, message direct ou
 * réponse), dans sa langue et sans rien dévoiler du contenu. Rien pour un compte qui ne pourrait
 * pas se connecter pour le lire, ni pour un admin.
 *
 * Aucune exception ne remonte, quelle qu'elle soit : le message est déjà enregistré et reste lisible
 * dans la messagerie. Une exception relancerait aussi SendContactBroadcastMessageHandler, qui
 * recréerait tous les fils de la diffusion.
 *
 * Non `final` : doublé par les tests des services de messagerie.
 */
readonly class AdminMessageEmailNotifier
{
    private const string TEMPLATE = 'emails/admin_message.html.twig';

    public function __construct(
        private EmailInterface $emailService,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    public function notify(ContactThread $thread): void
    {
        if (! $this->acceptsTheEmail($thread->owner)) {
            return;
        }

        try {
            $this->emailService->sendEmail($this->createEmail($thread));
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send the new admin message email.', [
                'threadId' => $thread->id,
                'exception' => $exception,
            ]);
        }
    }

    private function acceptsTheEmail(User $recipient): bool
    {
        return $recipient->emailOnAdminMessage
            && $recipient->isVerified
            && ! $recipient->isAccountBlocked
            && null === $recipient->deletionRequestedAt
            && ! \in_array('ROLE_ADMIN', $recipient->getRoles(), true);
    }

    private function createEmail(ContactThread $thread): TemplatedEmail
    {
        $recipient = $thread->owner;

        return $this->emailService->createEmail(
            $recipient->email,
            $this->translator->trans('messagerie.email.subject', [
                'brand' => $this->translator->trans('name', [], 'brand', $recipient->locale),
            ], 'navigation', $recipient->locale),
            [
                'threadId' => $thread->id?->toRfc4122(),
                'locale' => $recipient->locale,
            ],
            self::TEMPLATE,
            $recipient->locale,
        );
    }
}
