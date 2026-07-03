<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\User;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class UserAvatarService
{
    public function __construct(
        private ImageUploadService $imageUploadService,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function replace(User $user, UploadedFile $file): string
    {
        if (null === $user->id) {
            throw new \LogicException('Cannot upload an avatar for a user without a persisted id.');
        }

        $oldPath = $user->avatarPath;

        $path = $this->imageUploadService->upload(
            $file,
            'avatars',
            $user->id->toRfc4122(),
        );

        $user->avatarPath = $path;
        $this->em->flush();

        $this->imageUploadService->delete($oldPath);

        return $path;
    }

    /**
     * @throws FilesystemException
     */
    public function remove(User $user): void
    {
        $oldPath = $user->avatarPath;

        $user->avatarPath = null;
        $this->em->flush();

        $this->imageUploadService->delete($oldPath);
    }
}
