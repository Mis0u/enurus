<?php

declare(strict_types=1);

namespace App\Service\Contact;

use App\Entity\ContactThreadMessage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Attache une image optionnelle à un message de messagerie contact — logique partagée entre
 * la création d'un fil (ContactThreadService) et une réponse (ContactThreadReplyService).
 * La classe utilisatrice doit injecter `ImageUploadService $imageUploadService`.
 */
trait AttachesContactImageTrait
{
    private const string CONTACT_IMAGE_UPLOAD_CONTEXT = 'contact';

    private function attachImageIfPresent(ContactThreadMessage $message, ?UploadedFile $image, string $ownerId): void
    {
        if (null === $image) {
            return;
        }

        $message->imagePath = $this->imageUploadService->upload(
            $image,
            self::CONTACT_IMAGE_UPLOAD_CONTEXT,
            $ownerId,
        );
    }
}
