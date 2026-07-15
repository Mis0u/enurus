<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\ContactThread;
use App\Enum\Contact\ContactThreadStatusEnum;
use Doctrine\ORM\EntityManagerInterface;

readonly class ContactThreadCloseService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function close(ContactThread $thread): void
    {
        $thread->status = ContactThreadStatusEnum::CLOSED;
        $thread->closedAt = new \DateTimeImmutable();

        $this->entityManager->flush();
    }
}
