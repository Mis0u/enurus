<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Security;

use App\Entity\DeletedAccountTrace;
use App\Entity\User;
use App\Repository\DeletedAccountTraceRepository;
use App\Service\Email\EmailInterface;
use App\Service\Security\DeletedAccountReregistrationNotifierService;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DeletedAccountReregistrationNotifierServiceTest extends TestCase
{
    public function testNoTraceMeansNoEmailSent(): void
    {
        $user = new User();
        $user->email = 'never-deleted@test.com';

        $traceRepository = $this->createStub(DeletedAccountTraceRepository::class);
        $traceRepository->method('findByEmailHash')->willReturn(null);

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::never())->method('createEmail');
        $emailService->expects(self::never())->method('sendEmail');

        $translator = $this->createStub(TranslatorInterface::class);

        $service = new DeletedAccountReregistrationNotifierService($traceRepository, $emailService, $translator, 'admin@test.com');
        $service->notifyIfReregistration($user);
    }

    public function testMatchingTraceSendsAdminNotification(): void
    {
        $user = new User();
        $user->email = 'REJOINED@test.com';

        $trace = new DeletedAccountTrace();
        $trace->emailHash = hash('sha256', 'rejoined@test.com');
        $trace->deletedAt = new \DateTimeImmutable('-3 days');

        $traceRepository = $this->createMock(DeletedAccountTraceRepository::class);
        $traceRepository->expects(self::once())
            ->method('findByEmailHash')
            ->with(hash('sha256', 'rejoined@test.com'))
            ->willReturn($trace);

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::once())
            ->method('createEmail')
            ->with(
                'admin@test.com',
                self::isString(),
                self::callback(static fn (array $context): bool => 'REJOINED@test.com' === $context['reregisteredEmail']),
                'emails/admin_reregistration_notice.html.twig',
                'fr',
            )
            ->willReturn(new TemplatedEmail());
        $emailService->expects(self::once())->method('sendEmail');

        $translator = $this->createStub(TranslatorInterface::class);

        $service = new DeletedAccountReregistrationNotifierService($traceRepository, $emailService, $translator, 'admin@test.com');
        $service->notifyIfReregistration($user);
    }
}
