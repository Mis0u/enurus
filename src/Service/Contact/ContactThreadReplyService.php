<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class ContactThreadReplyService
{
    private const string IMAGE_UPLOAD_CONTEXT = 'contact';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ImageUploadService $imageUploadService,
    ) {
    }

    public function reply(
        User $author,
        ContactThread $thread,
        string $body,
        ?UploadedFile $image,
        bool $fromAdmin,
    ): ContactThreadMessage {
        if (null === $author->id) {
            throw new \LogicException('Cannot reply to a contact thread with a user without a persisted id.');
        }

        $message = new ContactThreadMessage();
        $message->author = $author;
        $message->fromAdmin = $fromAdmin;
        $message->body = $body;

        if (null !== $image) {
            $message->imagePath = $this->imageUploadService->upload(
                $image,
                self::IMAGE_UPLOAD_CONTEXT,
                $author->id->toRfc4122(),
            );
        }

        $thread->addMessage($message);
        $thread->status = $fromAdmin ? ContactThreadStatusEnum::ANSWERED_BY_ADMIN : ContactThreadStatusEnum::AWAITING_ADMIN_REPLY;
        $thread->updatedAt = new \DateTimeImmutable();

        $this->entityManager->flush();

        return $message;
    }
}
