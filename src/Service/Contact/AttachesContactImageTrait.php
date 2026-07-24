<?php

declare(strict_types=1);

namespace App\Service\Contact;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Upload d'une image optionnelle liée à un message de messagerie contact — logique partagée entre
 * la création d'un fil (ContactThreadService), une réponse (ContactThreadReplyService) et un envoi
 * groupé (ContactThreadComposeService). La classe utilisatrice doit injecter
 * `ImageUploadService $imageUploadService`.
 */
trait AttachesContactImageTrait
{
    private const string CONTACT_IMAGE_UPLOAD_CONTEXT = 'contact';

    private function uploadContactImage(?UploadedFile $image, string $ownerId): ?string
    {
        if (null === $image) {
            return null;
        }

        return $this->imageUploadService->upload($image, self::CONTACT_IMAGE_UPLOAD_CONTEXT, $ownerId);
    }
}
