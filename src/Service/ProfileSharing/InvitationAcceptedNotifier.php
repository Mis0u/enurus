<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Service\Email\EmailInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Prévient le parrain, dans sa langue, que son invité a rejoint l'app et qu'ils sont connectés.
 * Suit le même interrupteur que les demandes de connexion (`emailOnConnectionRequest`), pas de
 * réglage dédié. File `async` ; un échec d'envoi est loggé, jamais remonté — la connexion est déjà
 * enregistrée.
 *
 * Non `final` : doublé par `InvitationConnectionServiceTest`.
 */
readonly class InvitationAcceptedNotifier
{
    private const string TEMPLATE = 'emails/profile_connection_invitation_accepted.html.twig';

    public function __construct(
        private EmailInterface $emailService,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    public function notify(ProfileConnection $connection): void
    {
        if (! $connection->requester->emailOnConnectionRequest) {
            return;
        }

        try {
            $this->emailService->sendEmail($this->createEmail($connection));
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Failed to send the invitation accepted email.', [
                'connectionId' => $connection->id,
                'exception' => $exception,
            ]);
        }
    }

    private function createEmail(ProfileConnection $connection): TemplatedEmail
    {
        $inviter = $connection->requester;
        $inviteeNickname = $connection->addressee->nickname;

        return $this->emailService->createEmail(
            $inviter->email,
            $this->translator->trans('profile_connection.email.invitation_accepted.subject', [
                'nickname' => $inviteeNickname,
            ], 'navigation', $inviter->locale),
            [
                'inviteeNickname' => $inviteeNickname,
                'locale' => $inviter->locale,
            ],
            self::TEMPLATE,
            $inviter->locale,
        );
    }
}
