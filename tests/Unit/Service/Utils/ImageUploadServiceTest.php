<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Utils;

use App\Service\Utils\ImageUploadService;
use App\Tests\Functional\Helper\ImageTestHelper;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;

final class ImageUploadServiceTest extends TestCase
{
    public function testUploadWritesTheStreamUnderContextOwnerAndAGeneratedFilename(): void
    {
        $file = ImageTestHelper::createFakeImage('photo.jpg', 'image/jpeg');

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects(self::once())
            ->method('writeStream')
            ->with(self::callback(
                static fn (string $path): bool => (bool) preg_match('#^contact/owner-1/[0-9a-f-]{36}\.jpg$#', $path)
            ));

        $service = new ImageUploadService($storage);
        $path = $service->upload($file, 'contact', 'owner-1');

        self::assertMatchesRegularExpression('#^contact/owner-1/[0-9a-f-]{36}\.jpg$#', $path);
    }

    public function testUploadUsesTheDetectedExtensionOfThePngFile(): void
    {
        $file = ImageTestHelper::createFakeImage('photo.png', 'image/png');

        $storage = $this->createStub(FilesystemOperator::class);

        $service = new ImageUploadService($storage);
        $path = $service->upload($file, 'contact', 'owner-1');

        self::assertStringEndsWith('.png', $path);
    }

    public function testCopyDuplicatesTheFileToANewPathKeepingTheExtension(): void
    {
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects(self::once())
            ->method('copy')
            ->with('contact/source/original.jpg', self::callback(
                static fn (string $path): bool => (bool) preg_match('#^contact/owner-1/[0-9a-f-]{36}\.jpg$#', $path)
            ));

        $service = new ImageUploadService($storage);
        $destination = $service->copy('contact/source/original.jpg', 'contact', 'owner-1');

        self::assertStringEndsWith('.jpg', $destination);
    }

    public function testCopyWithNoExtensionInSourcePathGeneratesADestinationWithoutOne(): void
    {
        $storage = $this->createStub(FilesystemOperator::class);

        $service = new ImageUploadService($storage);
        $destination = $service->copy('contact/source/original', 'contact', 'owner-1');

        self::assertMatchesRegularExpression('#^contact/owner-1/[0-9a-f-]{36}$#', $destination);
    }

    public function testDeleteWithNullPathDoesNothing(): void
    {
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects(self::never())->method('fileExists');
        $storage->expects(self::never())->method('delete');

        $service = new ImageUploadService($storage);
        $service->delete(null);
    }

    public function testDeleteRemovesTheFileWhenItExists(): void
    {
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->method('fileExists')->with('contact/owner-1/photo.jpg')->willReturn(true);
        $storage->expects(self::once())->method('delete')->with('contact/owner-1/photo.jpg');

        $service = new ImageUploadService($storage);
        $service->delete('contact/owner-1/photo.jpg');
    }

    public function testDeleteDoesNothingWhenTheFileIsAlreadyGone(): void
    {
        $storage = $this->createMock(FilesystemOperator::class);
        $storage->method('fileExists')->willReturn(false);
        $storage->expects(self::never())->method('delete');

        $service = new ImageUploadService($storage);
        $service->delete('contact/owner-1/photo.jpg');
    }
}
