<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Security;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\RegistrationMilestoneSetting;
use App\Entity\User;
use App\Repository\RegistrationMilestoneSettingRepository;
use App\Repository\UserRepository;
use App\Service\Email\EmailInterface;
use App\Service\Security\RegistrationMilestoneNotifierService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegistrationMilestoneNotifierServiceTest extends TestCase
{
    public function testMilestoneNotJustCrossedMeansNoNotification(): void
    {
        $setting = new RegistrationMilestoneSetting();
        $setting->step = 500;

        $settingRepository = $this->createStub(RegistrationMilestoneSettingRepository::class);
        $settingRepository->method('getSingleton')->willReturn($setting);

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('countExcludingAdmins')->willReturn(501);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::never())->method('sendEmail');

        $service = $this->buildService($settingRepository, $userRepository, $entityManager, $emailService);
        $service->notifyIfMilestoneReached();
    }

    public function testMilestoneJustCrossedCreatesThreadAndSendsEmail(): void
    {
        $setting = new RegistrationMilestoneSetting();
        $setting->step = 500;

        $settingRepository = $this->createStub(RegistrationMilestoneSettingRepository::class);
        $settingRepository->method('getSingleton')->willReturn($setting);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('countExcludingAdmins')->willReturn(500);

        $admin = new User();
        $admin->email = 'admin@test.com';
        $admin->locale = 'fr';

        $userRepository->expects(self::once())
            ->method('findOneByEmail')
            ->with('admin@test.com')
            ->willReturn($admin)
        ;

        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                $persisted = $entity;
            })
        ;
        $entityManager->expects(self::once())->method('flush');

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->method('createEmail')->willReturn(new TemplatedEmail());
        $emailService->expects(self::once())->method('sendEmail');

        $service = $this->buildService($settingRepository, $userRepository, $entityManager, $emailService);
        $service->notifyIfMilestoneReached();

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
        $setting = new RegistrationMilestoneSetting();
        $setting->step = 500;

        $settingRepository = $this->createStub(RegistrationMilestoneSettingRepository::class);
        $settingRepository->method('getSingleton')->willReturn($setting);

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('countExcludingAdmins')->willReturn(500);
        $userRepository->method('findOneByEmail')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::never())->method('sendEmail');

        $service = $this->buildService($settingRepository, $userRepository, $entityManager, $emailService);

        $this->expectException(\LogicException::class);
        $service->notifyIfMilestoneReached();
    }

    public function testEmailFailureIsLoggedAndDoesNotPreventThreadCreation(): void
    {
        $setting = new RegistrationMilestoneSetting();
        $setting->step = 500;

        $settingRepository = $this->createStub(RegistrationMilestoneSettingRepository::class);
        $settingRepository->method('getSingleton')->willReturn($setting);

        $admin = new User();
        $admin->email = 'admin@test.com';
        $admin->locale = 'fr';

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('countExcludingAdmins')->willReturn(1000);
        $userRepository->method('findOneByEmail')->willReturn($admin);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->method('createEmail')->willReturn(new TemplatedEmail());
        $emailService->method('sendEmail')->willThrowException(new TransportException('mailer down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $service = $this->buildService($settingRepository, $userRepository, $entityManager, $emailService, $logger);
        $service->notifyIfMilestoneReached();
    }

    private function buildService(
        RegistrationMilestoneSettingRepository $settingRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        EmailInterface $emailService,
        ?LoggerInterface $logger = null,
    ): RegistrationMilestoneNotifierService {
        $translator = $this->createStub(TranslatorInterface::class);

        return new RegistrationMilestoneNotifierService(
            $userRepository,
            $settingRepository,
            $entityManager,
            $emailService,
            $translator,
            $logger ?? $this->createStub(LoggerInterface::class),
            'admin@test.com',
        );
    }
}
