<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class ContactThreadService
{
    private const string IMAGE_UPLOAD_CONTEXT = 'contact';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ImageUploadService $imageUploadService,
    ) {
    }

    public function create(
        User $user,
        ContactCategoryEnum $category,
        string $subject,
        string $body,
        ?UploadedFile $image,
    ): ContactThread {
        if (null === $user->id) {
            throw new \LogicException('Cannot create a contact thread for a user without a persisted id.');
        }

        $thread = new ContactThread();
        $thread->owner = $user;
        $thread->category = $category;
        $thread->subject = $subject;

        $message = new ContactThreadMessage();
        $message->author = $user;
        $message->fromAdmin = false;
        $message->body = $body;

        if (null !== $image) {
            $message->imagePath = $this->imageUploadService->upload(
                $image,
                self::IMAGE_UPLOAD_CONTEXT,
                $user->id->toRfc4122(),
            );
        }

        $thread->addMessage($message);

        $this->entityManager->persist($thread);
        $this->entityManager->flush();

        return $thread;
    }
}
