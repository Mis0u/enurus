<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Contact;

use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Service\Contact\ContactThreadService;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class ContactThreadServiceTest extends TestCase
{
    public function testCreateBuildsAThreadOwnedAndAuthoredByTheUser(): void
    {
        $user = $this->createUser();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $service = new ContactThreadService($em, $this->createStub(ImageUploadService::class));
        $thread = $service->create($user, ContactCategoryEnum::BUG, 'Sujet', 'Un souci rencontré', null);

        self::assertSame($user, $thread->owner);
        self::assertSame(ContactCategoryEnum::BUG, $thread->category);
        self::assertCount(1, $thread->messages);

        $message = $thread->messages->first();
        self::assertNotFalse($message);
        self::assertSame($user, $message->author);
        self::assertFalse($message->fromAdmin);
        self::assertSame('Un souci rencontré', $message->body);
    }

    public function testCreateUploadsTheOptionalImageUnderTheUsersId(): void
    {
        $user = $this->createUser();
        $image = $this->createStub(UploadedFile::class);

        if (! $user->id instanceof Uuid) {
            throw new \LogicException('Expected a persisted user.');
        }

        $imageUploadService = $this->createMock(ImageUploadService::class);
        $imageUploadService->expects(self::once())
            ->method('upload')
            ->with($image, 'contact', $user->id->toRfc4122())
            ->willReturn('contact/uploaded.jpg');

        $em = $this->createStub(EntityManagerInterface::class);
        $service = new ContactThreadService($em, $imageUploadService);
        $thread = $service->create($user, ContactCategoryEnum::BUG, 'Sujet', 'Corps', $image);

        $message = $thread->messages->first();
        self::assertNotFalse($message);
        self::assertSame('contact/uploaded.jpg', $message->imagePath);
    }

    public function testCreateWithAUserWithoutAPersistedIdThrows(): void
    {
        $user = new User();
        $user->email = 'user@test.com';
        $user->nickname = 'User';

        $service = new ContactThreadService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(ImageUploadService::class),
        );

        $this->expectException(\LogicException::class);

        $service->create($user, ContactCategoryEnum::BUG, 'Sujet', 'Corps', null);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->id = Uuid::v4();
        $user->email = 'user@test.com';
        $user->nickname = 'User';

        return $user;
    }
}
