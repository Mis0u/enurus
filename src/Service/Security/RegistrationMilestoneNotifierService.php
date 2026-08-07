<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Repository\RegistrationMilestoneSettingRepository;
use App\Repository\UserRepository;
use App\Service\Email\EmailInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Notifie l'admin (messagerie interne + email) à chaque palier de X inscriptions (500ème, 1000ème
 * user...), X piloté en admin via RegistrationMilestoneSetting. Ne bloque jamais l'inscription.
 */
final readonly class RegistrationMilestoneNotifierService
{
    public function __construct(
        private UserRepository $userRepository,
        private RegistrationMilestoneSettingRepository $settingRepository,
        private EntityManagerInterface $entityManager,
        private EmailInterface $emailService,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private string $adminUserEmail,
    ) {
    }

    public function notifyIfMilestoneReached(): void
    {
        $step = $this->settingRepository->getSingleton()->step;
        $currentCount = $this->userRepository->countExcludingAdmins();

        if (! $this->milestoneJustCrossed($currentCount, $step)) {
            return;
        }

        $admin = $this->userRepository->findOneByEmail($this->adminUserEmail);

        if (null === $admin) {
            throw new \LogicException(sprintf('Admin account "%s" not found — cannot notify about registration milestone.', $this->adminUserEmail));
        }

        $this->notifyByThread($admin, $currentCount);
        $this->notifyByEmail($admin, $currentCount);
    }

    private function milestoneJustCrossed(int $currentCount, int $step): bool
    {
        return intdiv($currentCount, $step) > intdiv($currentCount - 1, $step);
    }

    private function notifyByThread(User $admin, int $count): void
    {
        $thread = new ContactThread();
        $thread->owner = $admin;
        $thread->category = ContactCategoryEnum::INFORMATIVE;
        $thread->subject = $this->translator->trans('admin.registration_milestone.subject', [
            'count' => $count,
        ], 'navigation', $admin->locale);

        $message = new ContactThreadMessage();
        $message->author = $admin;
        $message->fromAdmin = true;
        $message->body = $this->translator->trans('admin.registration_milestone.body', [
            'count' => $count,
        ], 'navigation', $admin->locale);

        $thread->addMessage($message);

        $this->entityManager->persist($thread);
        $this->entityManager->flush();
    }

    /**
     * Échec d'envoi capturé et loggé plutôt que remonté — la notification interne (thread) a déjà
     * eu lieu, l'email n'est qu'un canal supplémentaire, pas la source de vérité.
     */
    private function notifyByEmail(User $admin, int $count): void
    {
        $mail = $this->emailService->createEmail(
            $admin->email,
            $this->translator->trans('admin.registration_milestone.subject', [
                'count' => $count,
            ], 'navigation', $admin->locale),
            [
                'count' => $count,
                'locale' => $admin->locale,
            ],
            'admin/email/registration_milestone.html.twig',
            $admin->locale,
        );

        $mail->getHeaders()->addTextHeader('X-Bus-Transport', 'sync');

        try {
            $this->emailService->sendEmail($mail);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send registration milestone email.', [
                'count' => $count,
                'exception' => $e,
            ]);
        }
    }
}
