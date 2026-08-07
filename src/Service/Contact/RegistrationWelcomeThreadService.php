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

    public function create(User $user, string $locale): void
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
        $message->body = $this->translator->trans(
            'contact.welcome_thread.body',
            [
                'nickname' => $user->nickname,
                'library_url' => $this->urlGenerator->generate('app_exercise_list', [
                    '_locale' => $locale,
                ]),
                'routine_url' => $this->urlGenerator->generate('app_routine_list', [
                    '_locale' => $locale,
                ]),
                'workout_url' => $this->urlGenerator->generate('app_workout', [
                    '_locale' => $locale,
                ]),
            ],
            'navigation',
            $locale,
        );

        $thread->addMessage($message);

        $this->entityManager->persist($thread);
        $this->entityManager->flush();
    }
}
