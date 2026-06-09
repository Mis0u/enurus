<?php

declare(strict_types=1);

namespace App\Tests\Functional\Helper;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImageTestHelper
{
    public static function createFakeImage(string $filename, string $mimeType, int $size = 1024): UploadedFile
    {
        $path = sys_get_temp_dir() . '/' . $filename;

        match ($mimeType) {
            'image/jpeg' => self::writeJpeg($path, 10, 10),
            'image/png' => self::writePng($path, 10, 10),
            default => file_put_contents($path, str_repeat('x', $size)),
        };

        return new UploadedFile($path, $filename, $mimeType, null, true);
    }

    public static function createLargeJpeg(string $filename = 'large_photo.jpg'): UploadedFile
    {
        $path = sys_get_temp_dir() . '/' . $filename;

        self::writeJpeg($path, 4000, 4000, withNoise: true);

        return new UploadedFile($path, $filename, 'image/jpeg', null, true);
    }

    // ─── Privé ───────────────────────────────────────────────────

    private static function createImage(int $width, int $height): \GdImage
    {
        if (1 > $width || 1 > $height) {
            throw new \InvalidArgumentException('Width and height must be at least 1.');
        }

        return imagecreatetruecolor($width, $height);
    }

    private static function writeJpeg(string $path, int $width, int $height, bool $withNoise = false): void
    {
        $image = self::createImage($width, $height);

        if ($withNoise) {
            self::fillWithNoise($image, $width, $height);
        }

        imagejpeg($image, $path, 100);
        imagedestroy($image);
    }

    private static function writePng(string $path, int $width, int $height): void
    {
        $image = self::createImage($width, $height);
        imagepng($image, $path);
        imagedestroy($image);
    }

    private static function fillWithNoise(\GdImage $image, int $width, int $height): void
    {
        for ($x = 0; $x < $width; $x += 10) {
            for ($y = 0; $y < $height; $y += 10) {
                $color = imagecolorallocate($image, rand(0, 255), rand(0, 255), rand(0, 255));
                if (false === $color) {
                    continue;
                }
                imagefilledrectangle($image, $x, $y, $x + 10, $y + 10, $color);
            }
        }
    }
}
