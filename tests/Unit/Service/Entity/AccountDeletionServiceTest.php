<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\User;
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

        $service = new AccountDeletionService($em, $userRepository, $emailService, $translator, $imageUploadService);

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

        $service = new AccountDeletionService($em, $userRepository, $emailService, $translator, $imageUploadService);

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

        $service = new AccountDeletionService($em, $userRepository, $emailService, $translator, $imageUploadService);

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

        $service = new AccountDeletionService($em, $userRepository, $emailService, $translator, $imageUploadService);

        self::assertSame(0, $service->purgeExpired());
    }

    public function testGetDeletionDeadlineReturnsNullWithoutPendingRequest(): void
    {
        $user = new User();

        $em = $this->createStub(EntityManagerInterface::class);
        $emailService = $this->createStub(EmailInterface::class);
        $translator = $this->createStub(TranslatorInterface::class);
        $userRepository = $this->createStub(UserRepository::class);
        $imageUploadService = $this->createStub(ImageUploadService::class);

        $service = new AccountDeletionService($em, $userRepository, $emailService, $translator, $imageUploadService);

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

        $service = new AccountDeletionService($em, $userRepository, $emailService, $translator, $imageUploadService);

        $deadline = $service->getDeletionDeadline($user);

        self::assertNotNull($deadline);
        self::assertSame('2026-01-31 10:00:00', $deadline->format('Y-m-d H:i:s'));
    }
}
