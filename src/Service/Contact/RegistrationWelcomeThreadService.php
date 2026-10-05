<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class RegistrationWelcomeThreadService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        private string $adminEmail,
    ) {
    }

    /**
     * @param User|null $inviter parrain auquel l'utilisateur vient d'être connecté par son lien
     *                           d'invitation (InvitationConnectionService), mentionné dans le message
     */
    public function create(User $user, string $locale, ?User $inviter = null): void
    {
        $admin = $this->userRepository->findOneByEmail($this->adminEmail);

        if (null === $admin) {
            throw new \LogicException(sprintf('Admin account "%s" not found — cannot author the welcome thread.', $this->adminEmail));
        }

        $brand = $this->translator->trans('name', [], 'brand', $locale);

        $thread = new ContactThread();
        $thread->owner = $user;
        $thread->isWelcomeMessage = true;
        $thread->category = ContactCategoryEnum::INFORMATIVE;
        $thread->subject = $this->translator->trans('contact.welcome_thread.subject', [
            'brand' => $brand,
        ], 'navigation', $locale);
        $thread->status = ContactThreadStatusEnum::CLOSED;
        $thread->closedAt = new \DateTimeImmutable();

        $message = new ContactThreadMessage();
        $message->author = $admin;
        $message->fromAdmin = true;
        $message->body = $this->buildBody($user, $locale, $inviter);

        $thread->addMessage($message);

        $this->entityManager->persist($thread);
        $this->entityManager->flush();
    }

    /**
     * Message d'admin, affiché en HTML brut (liens) : un pseudo, libre de tout caractère, ne doit
     * jamais y injecter de balise.
     */
    private function escape(string $nickname): string
    {
        return htmlspecialchars($nickname, \ENT_QUOTES);
    }

    private function buildBody(User $user, string $locale, ?User $inviter): string
    {
        return $this->translator->trans('contact.welcome_thread.body', [
            'nickname' => $this->escape($user->nickname),
            'invitation' => $this->buildInvitationParagraph($locale, $inviter),
            'routine_url' => $this->urlGenerator->generate('app_routine_list', [
                '_locale' => $locale,
            ]),
            'help_url' => $this->urlGenerator->generate('app_help', [
                '_locale' => $locale,
            ]),
        ], 'navigation', $locale);
    }

    /**
     * Paragraphe vide sans parrain ; sinon terminé par une ligne vide, pour s'insérer tel quel
     * entre la salutation et la suite du message.
     */
    private function buildInvitationParagraph(string $locale, ?User $inviter): string
    {
        if (null === $inviter) {
            return '';
        }

        return $this->translator->trans('contact.welcome_thread.invitation', [
            'nickname' => $this->escape($inviter->nickname),
            'connections_url' => $this->urlGenerator->generate('app_profile_connection_list', [
                '_locale' => $locale,
            ]),
        ], 'navigation', $locale) . "\n\n";
    }
}
