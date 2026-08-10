<?php

declare(strict_types=1);

namespace App\Service\Utils;

use App\Enum\ExtensionImageEnum;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

readonly class ImageUploadService
{
    public function __construct(
        private FilesystemOperator $defaultStorage,
    ) {
    }

    public function upload(UploadedFile $file, string $context, string $ownerId): string
    {
        $extension = $file->guessExtension() ?? ExtensionImageEnum::JPG->value;
        $filename = \sprintf('%s.%s', Uuid::v4()->toRfc4122(), $extension);
        $path = \sprintf('%s/%s/%s', $context, $ownerId, $filename);

        $stream = fopen($file->getPathname(), 'r');

        try {
            $this->defaultStorage->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $path;
    }

    /**
     * Copie un fichier déjà stocké vers un nouveau chemin indépendant — utilisé pour une diffusion
     * groupée (une image uploadée une seule fois, puis dupliquée par destinataire) plutôt que de
     * relire le fichier source original à chaque fois.
     */
    public function copy(string $sourcePath, string $context, string $ownerId): string
    {
        $extension = pathinfo($sourcePath, \PATHINFO_EXTENSION);
        $filename = '' === $extension ? Uuid::v4()->toRfc4122() : \sprintf('%s.%s', Uuid::v4()->toRfc4122(), $extension);
        $destination = \sprintf('%s/%s/%s', $context, $ownerId, $filename);

        // Cloudflare R2 doesn't implement the S3 GetObjectAcl API (501 Not Implemented) — Flysystem's
        // AWS S3 adapter calls it by default before a copy to preserve the source's visibility,
        // which breaks every copy() in prod. Uploads are never public/private per-file here anyway.
        $this->defaultStorage->copy($sourcePath, $destination, [
            'retain_visibility' => false,
        ]);

        return $destination;
    }

    public function delete(?string $path): void
    {
        if (null === $path) {
            return;
        }

        if ($this->defaultStorage->fileExists($path)) {
            $this->defaultStorage->delete($path);
        }
    }
}
