<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Security;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\DeletedAccountTrace;
use App\Entity\User;
use App\Repository\DeletedAccountTraceRepository;
use App\Repository\UserRepository;
use App\Service\Security\DeletedAccountReregistrationNotifierService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DeletedAccountReregistrationNotifierServiceTest extends TestCase
{
    public function testNoTraceMeansNoThreadCreated(): void
    {
        $user = new User();
        $user->email = 'never-deleted@test.com';

        $traceRepository = $this->createStub(DeletedAccountTraceRepository::class);
        $traceRepository->method('findByEmailHash')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $userRepository = $this->createStub(UserRepository::class);
        $translator = $this->createStub(TranslatorInterface::class);

        $service = new DeletedAccountReregistrationNotifierService($traceRepository, $entityManager, $userRepository, $translator, 'admin@test.com');
        $service->notifyIfReregistration($user);
    }

    public function testMatchingTraceCreatesThreadOwnedByAdmin(): void
    {
        $user = new User();
        $user->email = 'REJOINED@test.com';

        $trace = new DeletedAccountTrace();
        $trace->emailHash = hash('sha256', 'rejoined@test.com');
        $trace->deletedAt = new \DateTimeImmutable('2026-01-01');

        $traceRepository = $this->createMock(DeletedAccountTraceRepository::class);
        $traceRepository->expects(self::once())
            ->method('findByEmailHash')
            ->with(hash('sha256', 'rejoined@test.com'))
            ->willReturn($trace);

        $admin = new User();
        $admin->email = 'admin@test.com';
        $admin->locale = 'fr';

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())
            ->method('findOneByEmail')
            ->with('admin@test.com')
            ->willReturn($admin);

        $translator = $this->createStub(TranslatorInterface::class);

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                $persisted = $entity;
            });
        $entityManager->expects(self::once())->method('flush');

        $service = new DeletedAccountReregistrationNotifierService($traceRepository, $entityManager, $userRepository, $translator, 'admin@test.com');
        $service->notifyIfReregistration($user);

        self::assertInstanceOf(ContactThread::class, $persisted);
        self::assertSame($admin, $persisted->owner);
        self::assertCount(1, $persisted->messages);

        $message = $persisted->messages->first();
        self::assertInstanceOf(ContactThreadMessage::class, $message);
        self::assertTrue($message->fromAdmin);
        self::assertSame($admin, $message->author);
    }

    public function testThrowsWhenAdminAccountNotFound(): void
    {
        $user = new User();
        $user->email = 'REJOINED@test.com';

        $trace = new DeletedAccountTrace();
        $trace->emailHash = hash('sha256', 'rejoined@test.com');
        $trace->deletedAt = new \DateTimeImmutable();

        $traceRepository = $this->createStub(DeletedAccountTraceRepository::class);
        $traceRepository->method('findByEmailHash')->willReturn($trace);

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneByEmail')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $translator = $this->createStub(TranslatorInterface::class);

        $service = new DeletedAccountReregistrationNotifierService($traceRepository, $entityManager, $userRepository, $translator, 'missing-admin@test.com');

        $this->expectException(\LogicException::class);
        $service->notifyIfReregistration($user);
    }
}
