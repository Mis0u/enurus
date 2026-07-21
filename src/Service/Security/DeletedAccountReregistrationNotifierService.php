<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Repository\DeletedAccountTraceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Notifie l'admin dans sa propre messagerie interne (pas par email) quand un utilisateur se
 * réinscrit avec un email correspondant à un compte supprimé récemment (trace toujours présente,
 * purgée après 6 mois — voir AccountDeletionService::purgeExpiredTraces()). Ne bloque jamais
 * l'inscription, se contente d'alerter pour une éventuelle vérification manuelle d'abus.
 */
final readonly class DeletedAccountReregistrationNotifierService
{
    public function __construct(
        private DeletedAccountTraceRepository $traceRepository,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
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

        $admin = $this->userRepository->findOneByEmail($this->adminUserEmail);

        if (null === $admin) {
            throw new \LogicException(sprintf('Admin account "%s" not found — cannot notify about reregistration.', $this->adminUserEmail));
        }

        $thread = new ContactThread();
        $thread->owner = $admin;
        $thread->category = ContactCategoryEnum::INFORMATIVE;
        $thread->subject = $this->translator->trans('admin.reregistration.subject', [], 'navigation', $admin->locale);

        $message = new ContactThreadMessage();
        $message->author = $admin;
        $message->fromAdmin = true;
        $message->body = $this->translator->trans(
            'admin.reregistration.body',
            [
                'email' => $user->email,
                'deletedAt' => $trace->deletedAt->format('d/m/Y'),
            ],
            'navigation',
            $admin->locale,
        );

        $thread->addMessage($message);

        $this->entityManager->persist($thread);
        $this->entityManager->flush();
    }
}
