<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use League\Flysystem\FilesystemOperator;
use Twig\Attribute\AsTwigFilter;

final readonly class StorageUrlExtension
{
    public function __construct(
        private FilesystemOperator $defaultStorage,
    ) {
    }

    #[AsTwigFilter('storage_url')]
    public function storageUrl(?string $path): string
    {
        if (null === $path) {
            return '';
        }

        return $this->defaultStorage->publicUrl($path);
    }
}
