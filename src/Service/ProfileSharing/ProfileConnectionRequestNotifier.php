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
 * Prévient le destinataire d'une demande de connexion par email, dans sa langue, sauf s'il a coupé
 * ces emails. Passe par la file `async` (pas d'urgence) ; un échec d'envoi est loggé, jamais remonté
 * — la demande est déjà enregistrée et reste visible sur sa page Connexions.
 *
 * Non `final` : doublé par `ProfileConnectionRequestServiceTest`.
 */
readonly class ProfileConnectionRequestNotifier
{
    private const string TEMPLATE = 'emails/profile_connection_request.html.twig';

    public function __construct(
        private EmailInterface $emailService,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    public function notify(ProfileConnection $connection): void
    {
        if (! $connection->addressee->emailOnConnectionRequest) {
            return;
        }

        try {
            $this->emailService->sendEmail($this->createEmail($connection));
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Failed to send the connection request email.', [
                'connectionId' => $connection->id,
                'exception' => $exception,
            ]);
        }
    }

    private function createEmail(ProfileConnection $connection): TemplatedEmail
    {
        $addressee = $connection->addressee;
        $requesterNickname = $connection->requester->nickname;

        return $this->emailService->createEmail(
            $addressee->email,
            $this->translator->trans('profile_connection.email.request.subject', [
                'nickname' => $requesterNickname,
            ], 'navigation', $addressee->locale),
            [
                'requesterNickname' => $requesterNickname,
                'locale' => $addressee->locale,
            ],
            self::TEMPLATE,
            $addressee->locale,
        );
    }
}
