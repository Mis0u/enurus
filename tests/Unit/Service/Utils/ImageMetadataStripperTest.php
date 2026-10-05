<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Utils;

use App\Service\Utils\ImageMetadataStripper;
use PHPUnit\Framework\TestCase;

final class ImageMetadataStripperTest extends TestCase
{
    private const string SECRET = 'SECRET-GPS-48.8566';

    private const int EXIF_ORIENTATION_ROTATE_CLOCKWISE = 6;

    private const int WIDTH = 20;

    private const int HEIGHT = 10;

    /**
     * @var list<string>
     */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map(unlink(...), array_filter($this->files, is_file(...)));
    }

    public function testRemovesTheExifDataOfAJpeg(): void
    {
        $stripped = $this->strip($this->jpegWithExif(orientation: 1));

        self::assertStringNotContainsString(self::SECRET, $stripped);
        self::assertSame(IMAGETYPE_JPEG, getimagesizefromstring($stripped)[2] ?? null);
    }

    public function testKeepsAPhoneJpegUprightOnceItsOrientationTagIsGone(): void
    {
        $stripped = $this->strip($this->jpegWithExif(self::EXIF_ORIENTATION_ROTATE_CLOCKWISE));

        $size = getimagesizefromstring($stripped) ?: throw new \LogicException('Invalid image.');
        self::assertSame([self::HEIGHT, self::WIDTH], [$size[0], $size[1]]);
    }

    public function testRemovesTheTextChunksOfAPng(): void
    {
        $stripped = $this->strip($this->pngWithTextChunk());

        self::assertStringNotContainsString(self::SECRET, $stripped);
        self::assertSame(IMAGETYPE_PNG, getimagesizefromstring($stripped)[2] ?? null);
    }

    public function testKeepsTheTransparencyOfAPng(): void
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127) ?: 0);
        $path = $this->tempFile();
        imagepng($image, $path);

        $stripped = imagecreatefromstring($this->strip($path)) ?: throw new \LogicException('Invalid image.');

        $topLeftColor = imagecolorat($stripped, 0, 0) ?: throw new \LogicException('Unreadable pixel.');

        self::assertSame(127, imagecolorsforindex($stripped, $topLeftColor)['alpha']);
    }

    public function testKeepsAWebpAWebp(): void
    {
        $path = $this->tempFile();
        imagewebp(imagecreatetruecolor(self::WIDTH, self::HEIGHT), $path);

        self::assertSame(IMAGETYPE_WEBP, getimagesizefromstring($this->strip($path))[2] ?? null);
    }

    public function testRejectsAFileThatIsNotAnImage(): void
    {
        $path = $this->tempFile();
        file_put_contents($path, 'not an image');

        $this->expectException(\InvalidArgumentException::class);

        new ImageMetadataStripper()->strip($path);
    }

    private function strip(string $path): string
    {
        $stream = new ImageMetadataStripper()->strip($path);

        return stream_get_contents($stream) ?: throw new \LogicException('Empty stream.');
    }

    private function jpegWithExif(int $orientation): string
    {
        $path = $this->tempFile();
        imagejpeg(imagecreatetruecolor(self::WIDTH, self::HEIGHT), $path);
        $jpeg = file_get_contents($path) ?: throw new \LogicException('Unreadable JPEG.');

        // Segment APP1 inséré juste après le marqueur SOI, comme le fait un appareil photo.
        file_put_contents($path, substr($jpeg, 0, 2) . $this->exifSegment($orientation) . substr($jpeg, 2));

        return $path;
    }

    /**
     * TIFF little-endian, un seul IFD : Orientation (SHORT) + ImageDescription (ASCII) portant le
     * texte à faire disparaître.
     */
    private function exifSegment(int $orientation): string
    {
        $description = self::SECRET . "\0";
        $descriptionOffset = 8 + 2 + 2 * 12 + 4;

        $tiff = 'II' . pack('v', 42) . pack('V', 8)
            . pack('v', 2)
            . pack('vvVvv', 0x0112, 3, 1, $orientation, 0)
            . pack('vvVV', 0x010E, 2, \strlen($description), $descriptionOffset)
            . pack('V', 0)
            . $description;
        $payload = "Exif\0\0" . $tiff;

        return "\xFF\xE1" . pack('n', \strlen($payload) + 2) . $payload;
    }

    private function pngWithTextChunk(): string
    {
        $path = $this->tempFile();
        imagepng(imagecreatetruecolor(self::WIDTH, self::HEIGHT), $path);
        $png = file_get_contents($path) ?: throw new \LogicException('Unreadable PNG.');

        $data = 'Comment' . "\0" . self::SECRET;
        $chunk = pack('N', \strlen($data)) . 'tEXt' . $data . pack('N', crc32('tEXt' . $data));
        $signatureAndHeaderLength = 8 + 25;
        file_put_contents($path, substr($png, 0, $signatureAndHeaderLength) . $chunk . substr($png, $signatureAndHeaderLength));

        return $path;
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'strip') ?: throw new \LogicException('No temp file.');
        $this->files[] = $path;

        return $path;
    }
}
