<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\ContactThread;
use App\Repository\ContactThreadMessageRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ContactThreadReadService
{
    public function __construct(
        private ContactThreadMessageRepository $contactThreadMessageRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function markAdminMessagesAsRead(ContactThread $thread): void
    {
        $unreadMessages = $this->contactThreadMessageRepository->findUnreadAdminMessagesForThread($thread);

        if ([] === $unreadMessages) {
            return;
        }

        $now = new \DateTimeImmutable();

        foreach ($unreadMessages as $message) {
            $message->readAt = $now;
        }

        $this->entityManager->flush();
    }
}
