<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use App\Repository\DeletedAccountTraceRepository;
use App\Service\Email\EmailInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Notifie l'admin par email quand un utilisateur se réinscrit avec un email correspondant à un
 * compte supprimé récemment (trace toujours présente, purgée après 30 jours — voir
 * AccountDeletionService::purgeExpiredTraces()). Ne bloque jamais l'inscription, se contente
 * d'alerter pour une éventuelle vérification manuelle d'abus.
 */
final readonly class DeletedAccountReregistrationNotifierService
{
    private const string ADMIN_LOCALE = 'fr';

    public function __construct(
        private DeletedAccountTraceRepository $traceRepository,
        private EmailInterface $emailService,
        private TranslatorInterface $translator,
        private string $adminUserEmail,
    ) {
    }

    public function notifyIfReregistration(User $user): void
    {
        $emailHash = hash('sha256', mb_strtolower(trim($user->email)));
        $trace = $this->traceRepository->findByEmailHash($emailHash);

        if (null === $trace) {
            return;
        }

        $email = $this->emailService->createEmail(
            $this->adminUserEmail,
            $this->translator->trans('admin.reregistration.subject', [], 'navigation', self::ADMIN_LOCALE),
            [
                'reregisteredEmail' => $user->email,
                'deletedAt' => $trace->deletedAt,
                'locale' => self::ADMIN_LOCALE,
            ],
            'emails/admin_reregistration_notice.html.twig',
            self::ADMIN_LOCALE,
        );

        $this->emailService->sendEmail($email);
    }
}
