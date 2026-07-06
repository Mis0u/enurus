<?php

declare(strict_types=1);

namespace App\Constraint;

final class ImageConstraints
{
    public const int MAX_SIZE_BYTES = self::MAX_SIZE_MEGABYTES * self::KILOBYTES_PER_MEGABYTE * self::BYTES_PER_KILOBYTE;

    public const string MAX_SIZE_WEIGHT = '5M';

    /**
     * @var list<string>
     */
    public const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const int BYTES_PER_KILOBYTE = 1024;

    private const int KILOBYTES_PER_MEGABYTE = 1024;

    private const int MAX_SIZE_MEGABYTES = 5;
}
