<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Controller\Admin\ContactThreadCrudController;
use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Repository\ContactNotificationSettingRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Notifie l'admin par Telegram à chaque nouveau message utilisateur dans un ContactThread (fil créé
 * ou réponse — jamais quand l'admin est lui-même l'auteur, cf. appelants). Échec d'envoi capturé et
 * loggé, ne bloque jamais le flux utilisateur — même pattern défensif que les notifiers email
 * existants (RegistrationMilestoneNotifierService).
 */
readonly class ContactThreadAdminNotifierService
{
    private const int BODY_PREVIEW_LENGTH = 150;

    public function __construct(
        private ChatterInterface $chatter,
        private AdminUrlGenerator $adminUrlGenerator,
        private UserRepository $userRepository,
        private ContactNotificationSettingRepository $contactNotificationSettingRepository,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private string $adminUserEmail,
    ) {
    }

    public function notifyNewMessage(ContactThread $thread, ContactThreadMessage $message): void
    {
        if (! $this->contactNotificationSettingRepository->getSingleton()->telegramNotificationsEnabled) {
            return;
        }

        if ($message->author->telegramNotificationsMuted) {
            return;
        }

        $admin = $this->userRepository->findOneByEmail($this->adminUserEmail);

        if (null === $admin) {
            throw new \LogicException(sprintf('Admin account "%s" not found — cannot notify about new contact message.', $this->adminUserEmail));
        }

        $locale = $admin->locale;
        $category = $this->translator->trans('contact.category.' . $thread->category->value, [], 'navigation', $locale);
        $url = $this->adminUrlGenerator
            ->setController(ContactThreadCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($thread->id)
            ->generateUrl()
        ;

        $text = implode("\n", [
            $this->translator->trans('admin.new_thread_message.title', [], 'navigation', $locale),
            '',
            $this->translator->trans('admin.new_thread_message.category', [
                'category' => $category,
            ], 'navigation', $locale),
            $this->translator->trans('admin.new_thread_message.subject', [
                'subject' => $thread->subject,
            ], 'navigation', $locale),
            $this->translator->trans('admin.new_thread_message.from', [
                'email' => $message->author->email,
            ], 'navigation', $locale),
            '',
            sprintf('« %s »', $this->truncateBody($message->body)),
            '',
            $this->translator->trans('admin.new_thread_message.link', [
                'url' => $url,
            ], 'navigation', $locale),
        ]);

        try {
            $this->chatter->send(new ChatMessage($text));
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send Telegram notification for new contact thread message.', [
                'threadId' => $thread->id,
                'exception' => $e,
            ]);
        }
    }

    private function truncateBody(string $body): string
    {
        if (self::BODY_PREVIEW_LENGTH >= mb_strlen($body)) {
            return $body;
        }

        return mb_substr($body, 0, self::BODY_PREVIEW_LENGTH) . '…';
    }
}
