<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\DeletedAccountTrace;
use App\Entity\User;
use App\Repository\DeletedAccountTraceRepository;
use App\Repository\UserRepository;
use App\Service\Email\EmailInterface;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use function Symfony\Component\Clock\now;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class AccountDeletionService
{
    private const int RETENTION_DAYS = 30;

    /**
     * Durée de conservation du hash d'email dans DeletedAccountTrace, à compter de la suppression
     * effective du compte (pas de la demande) — délibérément plus longue que la rétention du
     * compte lui-même, pour couvrir une fenêtre de détection de réinscription réaliste.
     */
    private const int TRACE_RETENTION_MONTHS = 6;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private DeletedAccountTraceRepository $deletedAccountTraceRepository,
        private EmailInterface $emailService,
        private TranslatorInterface $translator,
        private ImageUploadService $imageUploadService,
        private LoggerInterface $logger,
    ) {
    }

    public function requestDeletion(User $user): void
    {
        $user->deletionRequestedAt = now();
        $this->entityManager->flush();

        $this->sendEmail(
            $user,
            'settings.account_deletion.requested.subject',
            'emails/account_deletion_requested.html.twig',
            [
                'deadline' => $this->getDeletionDeadline($user),
            ],
        );
    }

    public function cancelDeletion(User $user): void
    {
        if (null === $user->deletionRequestedAt) {
            return;
        }

        $user->deletionRequestedAt = null;
        $this->entityManager->flush();

        $this->sendEmail($user, 'settings.account_deletion.cancelled.subject', 'emails/account_deletion_cancelled.html.twig');
    }

    public function purgeExpired(): int
    {
        $threshold = now()->modify(sprintf('-%d days', self::RETENTION_DAYS));
        $users = $this->userRepository->findPendingDeletionOlderThan($threshold);

        foreach ($users as $user) {
            $this->purgeUser($user);
        }

        $this->entityManager->flush();

        return \count($users);
    }

    /**
     * Suppression immédiate déclenchée par un admin (cas exceptionnel), hors flux de rétention de
     * 30 jours — même nettoyage (fichiers, trace, cascade) que `purgeExpired()`, sans en passer par
     * `deletionRequestedAt`.
     */
    public function deleteImmediately(User $user): void
    {
        $this->purgeUser($user);
        $this->entityManager->flush();
    }

    public function purgeExpiredTraces(): int
    {
        $threshold = now()->modify(sprintf('-%d months', self::TRACE_RETENTION_MONTHS));

        return $this->deletedAccountTraceRepository->deleteOlderThan($threshold);
    }

    public function getDeletionDeadline(User $user): ?\DateTimeImmutable
    {
        if (null === $user->deletionRequestedAt) {
            return null;
        }

        return $user->deletionRequestedAt->modify(sprintf('+%d days', self::RETENTION_DAYS));
    }

    /**
     * Envoyée en synchrone (hors file `async`) : une notification de sécurité doit arriver
     * immédiatement, sans dépendre du prochain passage d'un worker Messenger — même raisonnement
     * que `UserService::sendPasswordChangedEmail()`. Un échec d'envoi (mailer indisponible) ne
     * doit jamais faire échouer la demande/annulation/suppression elle-même (déjà persistée à ce
     * stade) — capturé et loggé plutôt que remonté à l'appelant.
     *
     * @param array<string, mixed> $extraContext
     */
    private function sendEmail(User $user, string $subjectKey, string $template, array $extraContext = []): void
    {
        $email = $this->emailService->createEmail(
            $user->email,
            $this->translator->trans($subjectKey, [], 'navigation', $user->locale),
            array_merge([
                'user' => $user,
                'locale' => $user->locale,
            ], $extraContext),
            $template,
            $user->locale,
        );

        $email->getHeaders()->addTextHeader('X-Bus-Transport', 'sync');

        try {
            $this->emailService->sendEmail($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send account-deletion email.', [
                'userId' => $user->id,
                'subjectKey' => $subjectKey,
                'exception' => $e,
            ]);
        }
    }

    private function purgeUser(User $user): void
    {
        $this->sendEmail($user, 'settings.account_deletion.deleted.subject', 'emails/account_deletion_deleted.html.twig');
        $this->recordDeletionTrace($user);
        $this->deletePhysicalFiles($user);
        $this->entityManager->remove($user);
    }

    private function recordDeletionTrace(User $user): void
    {
        $trace = new DeletedAccountTrace();
        $trace->emailHash = hash('sha256', mb_strtolower(trim($user->email)));
        $trace->deletedAt = now();

        $this->entityManager->persist($trace);
    }

    private function deletePhysicalFiles(User $user): void
    {
        $this->imageUploadService->delete($user->avatarPath);

        foreach ($user->workouts as $workout) {
            $this->imageUploadService->delete($workout->photoPath);
        }

        foreach ($user->contactThreads as $thread) {
            foreach ($thread->messages as $message) {
                $this->imageUploadService->delete($message->imagePath);
            }
        }
    }
}
