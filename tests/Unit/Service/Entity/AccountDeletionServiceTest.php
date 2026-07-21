<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Entity\Workout;
use App\Repository\DeletedAccountTraceRepository;
use App\Repository\UserRepository;
use App\Service\Email\EmailInterface;
use App\Service\Entity\AccountDeletionService;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AccountDeletionServiceTest extends TestCase
{
    public function testRequestDeletionSetsTimestampAndSendsEmail(): void
    {
        $user = new User();
        $user->email = 'test@example.com';
        $user->nickname = 'TestUser';
        $user->locale = 'fr';

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::once())
            ->method('createEmail')
            ->willReturn(new TemplatedEmail());
        $emailService->expects(self::once())->method('sendEmail');

        $translator = $this->createStub(TranslatorInterface::class);
        $userRepository = $this->createStub(UserRepository::class);
        $imageUploadService = $this->createStub(ImageUploadService::class);

        $deletedAccountTraceRepository = $this->createStub(DeletedAccountTraceRepository::class);
        $service = new AccountDeletionService($em, $userRepository, $deletedAccountTraceRepository, $emailService, $translator, $imageUploadService);

        self::assertNull($user->deletionRequestedAt);

        $service->requestDeletion($user);

        self::assertNotNull($user->deletionRequestedAt);
    }

    public function testCancelDeletionWithPendingRequestClearsTimestampAndSendsEmail(): void
    {
        $user = new User();
        $user->email = 'test@example.com';
        $user->nickname = 'TestUser';
        $user->locale = 'fr';
        $user->deletionRequestedAt = new \DateTimeImmutable('-5 days');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::once())
            ->method('createEmail')
            ->willReturn(new TemplatedEmail());
        $emailService->expects(self::once())->method('sendEmail');

        $translator = $this->createStub(TranslatorInterface::class);
        $userRepository = $this->createStub(UserRepository::class);
        $imageUploadService = $this->createStub(ImageUploadService::class);

        $deletedAccountTraceRepository = $this->createStub(DeletedAccountTraceRepository::class);
        $service = new AccountDeletionService($em, $userRepository, $deletedAccountTraceRepository, $emailService, $translator, $imageUploadService);

        $service->cancelDeletion($user);

        self::assertNull($user->deletionRequestedAt);
    }

    public function testCancelDeletionWithoutPendingRequestDoesNothing(): void
    {
        $user = new User();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('flush');

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::never())->method('createEmail');
        $emailService->expects(self::never())->method('sendEmail');

        $translator = $this->createStub(TranslatorInterface::class);
        $userRepository = $this->createStub(UserRepository::class);
        $imageUploadService = $this->createStub(ImageUploadService::class);

        $deletedAccountTraceRepository = $this->createStub(DeletedAccountTraceRepository::class);
        $service = new AccountDeletionService($em, $userRepository, $deletedAccountTraceRepository, $emailService, $translator, $imageUploadService);

        $service->cancelDeletion($user);

        self::assertNull($user->deletionRequestedAt);
    }

    public function testPurgeExpiredWithEmptyListReturnsZero(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())
            ->method('findPendingDeletionOlderThan')
            ->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');
        $em->expects(self::never())->method('remove');

        $emailService = $this->createStub(EmailInterface::class);
        $translator = $this->createStub(TranslatorInterface::class);
        $imageUploadService = $this->createStub(ImageUploadService::class);

        $deletedAccountTraceRepository = $this->createStub(DeletedAccountTraceRepository::class);
        $service = new AccountDeletionService($em, $userRepository, $deletedAccountTraceRepository, $emailService, $translator, $imageUploadService);

        self::assertSame(0, $service->purgeExpired());
    }

    public function testPurgeExpiredDeletesAvatarWorkoutAndContactThreadImages(): void
    {
        $user = new User();
        $user->email = 'test@example.com';
        $user->nickname = 'TestUser';
        $user->locale = 'fr';
        $user->avatarPath = 'avatars/avatar.jpg';
        $user->deletionRequestedAt = new \DateTimeImmutable('-35 days');

        $workout = new Workout();
        $workout->owner = $user;
        $workout->photoPath = 'workouts/photo.jpg';
        $user->workouts->add($workout);

        $thread = new ContactThread();
        $thread->owner = $user;
        $thread->subject = 'Test subject';

        $message = new ContactThreadMessage();
        $message->author = $user;
        $message->body = 'A message with an attached image.';
        $message->imagePath = 'contact/message.jpg';
        $thread->addMessage($message);

        $user->contactThreads->add($thread);

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findPendingDeletionOlderThan')->willReturn([$user]);

        $em = $this->createStub(EntityManagerInterface::class);
        $emailService = $this->createStub(EmailInterface::class);
        $emailService->method('createEmail')->willReturn(new TemplatedEmail());
        $translator = $this->createStub(TranslatorInterface::class);

        $deletedPaths = [];
        $imageUploadService = $this->createMock(ImageUploadService::class);
        $imageUploadService->expects(self::exactly(3))
            ->method('delete')
            ->willReturnCallback(function (?string $path) use (&$deletedPaths): void {
                $deletedPaths[] = $path;
            });

        $deletedAccountTraceRepository = $this->createStub(DeletedAccountTraceRepository::class);
        $service = new AccountDeletionService($em, $userRepository, $deletedAccountTraceRepository, $emailService, $translator, $imageUploadService);

        $service->purgeExpired();

        self::assertSame(
            ['avatars/avatar.jpg', 'workouts/photo.jpg', 'contact/message.jpg'],
            $deletedPaths,
        );
    }

    public function testGetDeletionDeadlineReturnsNullWithoutPendingRequest(): void
    {
        $user = new User();

        $em = $this->createStub(EntityManagerInterface::class);
        $emailService = $this->createStub(EmailInterface::class);
        $translator = $this->createStub(TranslatorInterface::class);
        $userRepository = $this->createStub(UserRepository::class);
        $imageUploadService = $this->createStub(ImageUploadService::class);

        $deletedAccountTraceRepository = $this->createStub(DeletedAccountTraceRepository::class);
        $service = new AccountDeletionService($em, $userRepository, $deletedAccountTraceRepository, $emailService, $translator, $imageUploadService);

        self::assertNull($service->getDeletionDeadline($user));
    }

    public function testGetDeletionDeadlineReturnsCorrectDate(): void
    {
        $user = new User();
        $user->deletionRequestedAt = new \DateTimeImmutable('2026-01-01 10:00:00');

        $em = $this->createStub(EntityManagerInterface::class);
        $emailService = $this->createStub(EmailInterface::class);
        $translator = $this->createStub(TranslatorInterface::class);
        $userRepository = $this->createStub(UserRepository::class);
        $imageUploadService = $this->createStub(ImageUploadService::class);

        $deletedAccountTraceRepository = $this->createStub(DeletedAccountTraceRepository::class);
        $service = new AccountDeletionService($em, $userRepository, $deletedAccountTraceRepository, $emailService, $translator, $imageUploadService);

        $deadline = $service->getDeletionDeadline($user);

        self::assertNotNull($deadline);
        self::assertSame('2026-01-31 10:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function testPurgeExpiredTracesDelegatesToRepositoryWithThresholdAndReturnsCount(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $emailService = $this->createStub(EmailInterface::class);
        $translator = $this->createStub(TranslatorInterface::class);
        $userRepository = $this->createStub(UserRepository::class);
        $imageUploadService = $this->createStub(ImageUploadService::class);

        $deletedAccountTraceRepository = $this->createMock(DeletedAccountTraceRepository::class);
        $deletedAccountTraceRepository->expects(self::once())
            ->method('deleteOlderThan')
            ->with(self::callback(static function (\DateTimeImmutable $threshold): bool {
                $expected = (new \DateTimeImmutable())->modify('-6 months');

                return 5 > abs($threshold->getTimestamp() - $expected->getTimestamp());
            }))
            ->willReturn(3);

        $service = new AccountDeletionService($em, $userRepository, $deletedAccountTraceRepository, $emailService, $translator, $imageUploadService);

        self::assertSame(3, $service->purgeExpiredTraces());
    }
}
