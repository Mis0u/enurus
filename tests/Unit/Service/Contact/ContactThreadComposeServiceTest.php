<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Contact;

use App\Entity\ContactBroadcast;
use App\Entity\User;
use App\Enum\Contact\ContactBroadcastTargetEnum;
use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Message\SendContactBroadcastMessage;
use App\Repository\UserRepository;
use App\Service\Contact\ContactMessageBodySanitizerService;
use App\Service\Contact\ContactThreadComposeService;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class ContactThreadComposeServiceTest extends TestCase
{
    public function testComposeToSingleUserSanitizesBodyAndMarksThreadAnswered(): void
    {
        $admin = $this->createUser();
        $recipient = $this->createUser();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $service = $this->service($em, $this->createStub(UserRepository::class), $this->createStub(MessageBusInterface::class));

        $thread = $service->composeToSingleUser(
            $admin,
            $recipient,
            ContactCategoryEnum::BUG,
            'Sujet',
            '<script>alert(1)</script><p>Réponse</p>',
            null,
        );

        self::assertSame($recipient, $thread->owner);
        self::assertSame(ContactThreadStatusEnum::ANSWERED_BY_ADMIN, $thread->status);

        $message = $thread->messages->first();
        self::assertNotFalse($message);
        self::assertSame('<p>Réponse</p>', $message->body);
        self::assertTrue($message->fromAdmin);
    }

    public function testComposeToSingleUserWithAnAdminWithoutAPersistedIdThrows(): void
    {
        $admin = new User();
        $admin->email = 'admin@test.com';
        $admin->nickname = 'Admin';
        $recipient = $this->createUser();

        $service = $this->service(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(UserRepository::class),
            $this->createStub(MessageBusInterface::class),
        );

        $this->expectException(\LogicException::class);

        $service->composeToSingleUser($admin, $recipient, ContactCategoryEnum::BUG, 'Sujet', 'Corps', null);
    }

    public function testComposeToAudienceDispatchesABroadcastMessageWithTheRecipientCount(): void
    {
        $admin = $this->createUser();

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('countForBroadcast')->willReturn(42);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof ContactBroadcast) {
                $entity->id = Uuid::v4();
            }
        });

        $dispatchedMessage = null;
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatchedMessage): Envelope {
                $dispatchedMessage = $message;

                return new Envelope($message);
            });

        $service = $this->service($em, $userRepository, $messageBus);

        $recipientCount = $service->composeToAudience(
            $admin,
            ContactCategoryEnum::INFORMATIVE,
            ContactBroadcastTargetEnum::ALL,
            null,
            'Sujet',
            'Corps',
            null,
        );

        self::assertSame(42, $recipientCount);
        self::assertInstanceOf(SendContactBroadcastMessage::class, $dispatchedMessage);
    }

    public function testComposeToAudienceWithVoteCategoryAttachesPollOptionsInOrder(): void
    {
        $admin = $this->createUser();

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('countForBroadcast')->willReturn(10);

        $capturedBroadcast = null;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$capturedBroadcast): void {
            if ($entity instanceof ContactBroadcast) {
                $entity->id = Uuid::v4();
                $capturedBroadcast = $entity;
            }
        });

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(
            static fn (object $message): Envelope => new Envelope($message),
        );

        $service = $this->service($em, $userRepository, $messageBus);

        $service->composeToAudience(
            $admin,
            ContactCategoryEnum::VOTE,
            ContactBroadcastTargetEnum::ALL,
            null,
            'Sondage',
            'Corps',
            null,
            pollOptionLabels: ['Oui', 'Non'],
            pollDurationDays: 7,
        );

        self::assertInstanceOf(ContactBroadcast::class, $capturedBroadcast);
        self::assertCount(2, $capturedBroadcast->pollOptions);
        self::assertNotNull($capturedBroadcast->pollClosesAt);
    }

    public function testComposeToAudienceWithVoteCategoryAndNoDurationThrows(): void
    {
        $admin = $this->createUser();

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('countForBroadcast')->willReturn(10);

        $service = $this->service(
            $this->createStub(EntityManagerInterface::class),
            $userRepository,
            $this->createStub(MessageBusInterface::class),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A poll broadcast requires a closing duration.');

        $service->composeToAudience(
            $admin,
            ContactCategoryEnum::VOTE,
            ContactBroadcastTargetEnum::ALL,
            null,
            'Sondage',
            'Corps',
            null,
            pollOptionLabels: ['Oui', 'Non'],
            pollDurationDays: null,
        );
    }

    public function testComposeToAudienceUploadsTheOptionalImageUnderTheAdminsId(): void
    {
        $admin = $this->createUser();
        $image = $this->createStub(UploadedFile::class);

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('countForBroadcast')->willReturn(1);

        if (! $admin->id instanceof Uuid) {
            throw new \LogicException('Expected a persisted admin.');
        }

        $imageUploadService = $this->createMock(ImageUploadService::class);
        $imageUploadService->expects(self::once())
            ->method('upload')
            ->with($image, 'contact', $admin->id->toRfc4122())
            ->willReturn('contact/uploaded.jpg');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof ContactBroadcast) {
                $entity->id = Uuid::v4();
            }
        });

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(
            static fn (object $message): Envelope => new Envelope($message),
        );

        $service = new ContactThreadComposeService(
            $em,
            $imageUploadService,
            new ContactMessageBodySanitizerService(),
            $userRepository,
            $messageBus,
        );

        $service->composeToAudience(
            $admin,
            ContactCategoryEnum::INFORMATIVE,
            ContactBroadcastTargetEnum::ALL,
            null,
            'Sujet',
            'Corps',
            $image,
        );
    }

    private function service(
        EntityManagerInterface $em,
        UserRepository $userRepository,
        MessageBusInterface $messageBus,
    ): ContactThreadComposeService {
        return new ContactThreadComposeService(
            $em,
            $this->createStub(ImageUploadService::class),
            new ContactMessageBodySanitizerService(),
            $userRepository,
            $messageBus,
        );
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
