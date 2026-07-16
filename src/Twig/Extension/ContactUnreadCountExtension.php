<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\User;
use App\Repository\ContactThreadMessageRepository;
use Twig\Attribute\AsTwigFunction;

final readonly class ContactUnreadCountExtension
{
    public function __construct(
        private ContactThreadMessageRepository $contactThreadMessageRepository,
    ) {
    }

    #[AsTwigFunction('contact_unread_count')]
    public function unreadCount(User $user): int
    {
        return $this->contactThreadMessageRepository->countUnreadForUser($user);
    }
}
