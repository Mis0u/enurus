<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Contact;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Repository\ContactThreadRepository;
use App\Service\Contact\ContactThreadPurgeService;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ContactThreadPurgeServiceTest extends TestCase
{
    public function testPurgeClosedRemovesEachThreadAndDeletesItsImages(): void
    {
        $threadWithImage = $this->createThread(imagePath: 'contact/one.jpg');
        $threadWithoutImage = $this->createThread(imagePath: null);

        $contactThreadRepository = $this->createStub(ContactThreadRepository::class);
        $contactThreadRepository->method('findClosedBefore')->willReturn([$threadWithImage, $threadWithoutImage]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('remove');
        $em->expects(self::once())->method('flush');

        $imageUploadService = $this->createMock(ImageUploadService::class);
        $imageUploadService->expects(self::exactly(2))->method('delete');

        $service = new ContactThreadPurgeService($em, $contactThreadRepository, $imageUploadService);

        self::assertSame(2, $service->purgeClosed());
    }

    public function testPurgeClosedWithNoThreadDoesNothing(): void
    {
        $contactThreadRepository = $this->createStub(ContactThreadRepository::class);
        $contactThreadRepository->method('findClosedBefore')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('remove');
        $em->expects(self::once())->method('flush');

        $imageUploadService = $this->createMock(ImageUploadService::class);
        $imageUploadService->expects(self::never())->method('delete');

        $service = new ContactThreadPurgeService($em, $contactThreadRepository, $imageUploadService);

        self::assertSame(0, $service->purgeClosed());
    }

    private function createThread(?string $imagePath): ContactThread
    {
        $owner = new User();
        $owner->email = 'user@test.com';
        $owner->nickname = 'User';

        $thread = new ContactThread();
        $thread->owner = $owner;
        $thread->category = ContactCategoryEnum::BUG;
        $thread->subject = 'Sujet de test';

        $message = new ContactThreadMessage();
        $message->author = $owner;
        $message->fromAdmin = false;
        $message->body = 'Message de test';
        $message->imagePath = $imagePath;
        $thread->addMessage($message);

        return $thread;
    }
}
