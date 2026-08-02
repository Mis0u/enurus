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

final readonly class ContactThreadService
{
    use AttachesContactImageTrait;
    use CreatesContactThreadTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ImageUploadService $imageUploadService,
        private ContactThreadAdminNotifierService $adminNotifier,
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

        $imagePath = $this->uploadContactImage($image, $user->id->toRfc4122());
        $thread = $this->buildThread($user, $user, $category, $subject, $body, $imagePath, fromAdmin: false);

        $this->entityManager->persist($thread);
        $this->entityManager->flush();

        $message = $thread->messages->first();

        if ($message instanceof ContactThreadMessage) {
            $this->adminNotifier->notifyNewMessage($thread, $message);
        }

        return $thread;
    }
}
