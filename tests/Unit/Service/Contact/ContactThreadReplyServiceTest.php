<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Contact;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Service\Contact\ContactMessageBodySanitizerService;
use App\Service\Contact\ContactThreadReplyService;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class ContactThreadReplyServiceTest extends TestCase
{
    public function testAdminReplySanitizesBodyAndMarksThreadAnswered(): void
    {
        $admin = $this->createUser();
        $thread = $this->createThread($admin);

        $imageUploadService = $this->createStub(ImageUploadService::class);
        $service = $this->service($imageUploadService);

        $message = $service->reply($admin, $thread, '<script>alert(1)</script><p>Réponse</p>', null, fromAdmin: true);

        self::assertSame('<p>Réponse</p>', $message->body);
        self::assertSame(ContactThreadStatusEnum::ANSWERED_BY_ADMIN, $thread->status);
        self::assertTrue($message->fromAdmin);
    }

    public function testUserReplyDoesNotSanitizeTheBodyAndMarksThreadAwaitingAdminReply(): void
    {
        $user = $this->createUser();
        $thread = $this->createThread($user);
        $thread->status = ContactThreadStatusEnum::ANSWERED_BY_ADMIN;

        $imageUploadService = $this->createStub(ImageUploadService::class);
        $service = $this->service($imageUploadService);

        // Le body utilisateur n'est jamais assaini (texte brut échappé à l'affichage) : la balise
        // reste telle quelle en base, contrairement à une réponse admin.
        $message = $service->reply($user, $thread, '<script>alert(1)</script>', null, fromAdmin: false);

        self::assertSame('<script>alert(1)</script>', $message->body);
        self::assertSame(ContactThreadStatusEnum::AWAITING_ADMIN_REPLY, $thread->status);
        self::assertFalse($message->fromAdmin);
    }

    public function testReplyWithAnImageUploadsItAndStoresThePath(): void
    {
        $user = $this->createUser();
        $thread = $this->createThread($user);
        $image = $this->createStub(UploadedFile::class);

        if (! $user->id instanceof Uuid) {
            throw new \LogicException('Expected a persisted user.');
        }

        $imageUploadService = $this->createMock(ImageUploadService::class);
        $imageUploadService->expects(self::once())
            ->method('upload')
            ->with($image, 'contact', $user->id->toRfc4122())
            ->willReturn('contact/uploaded.jpg');

        $service = $this->service($imageUploadService);
        $message = $service->reply($user, $thread, 'Réponse', $image, fromAdmin: false);

        self::assertSame('contact/uploaded.jpg', $message->imagePath);
    }

    public function testReplyAppendsTheMessageToTheThread(): void
    {
        $user = $this->createUser();
        $thread = $this->createThread($user);
        self::assertCount(1, $thread->messages);

        $service = $this->service($this->createStub(ImageUploadService::class));
        $service->reply($user, $thread, 'Réponse', null, fromAdmin: false);

        self::assertCount(2, $thread->messages);
    }

    private function service(ImageUploadService $imageUploadService): ContactThreadReplyService
    {
        return new ContactThreadReplyService(
            $this->createStub(EntityManagerInterface::class),
            $imageUploadService,
            new ContactMessageBodySanitizerService(),
        );
    }

    private function createThread(User $owner): ContactThread
    {
        $thread = new ContactThread();
        $thread->owner = $owner;
        $thread->category = ContactCategoryEnum::BUG;
        $thread->subject = 'Sujet de test';
        $thread->status = ContactThreadStatusEnum::AWAITING_ADMIN_REPLY;

        $message = new ContactThreadMessage();
        $message->author = $owner;
        $message->fromAdmin = false;
        $message->body = 'Message initial';
        $thread->addMessage($message);

        return $thread;
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
