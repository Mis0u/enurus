<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\ContactThread;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ContactThreadHideService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function hideForUser(ContactThread $thread): void
    {
        $thread->hiddenByUserAt = new \DateTimeImmutable();

        $this->entityManager->flush();
    }
}
