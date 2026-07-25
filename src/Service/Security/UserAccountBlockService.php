<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use App\Service\Email\EmailInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use function Symfony\Component\Clock\now;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Blocage du compte (connexion) — distinct de `ContactRestrictionService` (restriction de la
 * messagerie uniquement). Un compte bloqué est immédiatement déconnecté de partout et ne peut plus
 * se reconnecter (cf. BlockedUserChecker), y compris via le cookie "se souvenir de moi".
 */
final readonly class UserAccountBlockService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SessionInvalidationService $sessionInvalidationService,
        private EmailInterface $emailService,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
    ) {
    }

    public function block(User $user): void
    {
        $user->accountBlockedAt = now();
        $this->entityManager->flush();

        $this->sessionInvalidationService->invalidateAllSessions($user);
        $this->sendEmail($user, 'settings.account_blocked.subject', 'emails/account_blocked.html.twig');
    }

    public function unblock(User $user): void
    {
        $user->accountBlockedAt = null;
        $this->entityManager->flush();

        $this->sendEmail($user, 'settings.account_unblocked.subject', 'emails/account_unblocked.html.twig');
    }

    /**
     * Envoyée en synchrone (hors file `async`) : l'utilisateur doit être informé immédiatement
     * d'un changement d'accès à son compte. Un échec d'envoi ne doit jamais faire échouer le
     * blocage/déblocage lui-même (déjà persisté à ce stade) — capturé et loggé plutôt que remonté
     * à l'appelant (même pattern que UserService::sendPasswordChangedEmail()).
     */
    private function sendEmail(User $user, string $subjectKey, string $template): void
    {
        $email = $this->emailService->createEmail(
            $user->email,
            $this->translator->trans($subjectKey, [], 'navigation', $user->locale),
            [
                'user' => $user,
                'locale' => $user->locale,
            ],
            $template,
            $user->locale,
        );

        $email->getHeaders()->addTextHeader('X-Bus-Transport', 'sync');

        try {
            $this->emailService->sendEmail($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send account block/unblock email.', [
                'userId' => $user->id,
                'exception' => $e,
            ]);
        }
    }
}
