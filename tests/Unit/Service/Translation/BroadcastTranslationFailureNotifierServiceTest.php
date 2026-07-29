<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Translation;

use App\Entity\ContactBroadcast;
use App\Entity\User;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Exception\Translation\TranslationFailedException;
use App\Repository\UserRepository;
use App\Service\Email\EmailInterface;
use App\Service\Translation\BroadcastTranslationFailureNotifierService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BroadcastTranslationFailureNotifierServiceTest extends TestCase
{
    public function testDoesNothingAndLogsWhenAdminAccountNotFound(): void
    {
        $broadcast = new ContactBroadcast();
        $broadcast->subject = 'Annonce';

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneByEmail')->willReturn(null);

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::never())->method('createEmail');
        $emailService->expects(self::never())->method('sendEmail');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $translator = $this->createStub(TranslatorInterface::class);

        $service = new BroadcastTranslationFailureNotifierService($emailService, $userRepository, $translator, $logger, 'missing-admin@test.com');
        $service->notify($broadcast, LocaleAllowedEnum::DE, new TranslationFailedException('boom'));
    }

    public function testSendsRealEmailToAdminWhenFound(): void
    {
        $admin = new User();
        $admin->email = 'admin@test.com';
        $admin->locale = 'fr';

        $broadcast = new ContactBroadcast();
        $broadcast->subject = 'Annonce globale';

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneByEmail')->willReturn($admin);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Échec de traduction');

        $sentEmail = new TemplatedEmail();

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::once())
            ->method('createEmail')
            ->with('admin@test.com', 'Échec de traduction', self::isArray(), 'emails/broadcast_translation_failed.html.twig', 'fr')
            ->willReturn($sentEmail)
        ;
        $emailService->expects(self::once())->method('sendEmail')->with($sentEmail);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $service = new BroadcastTranslationFailureNotifierService($emailService, $userRepository, $translator, $logger, 'admin@test.com');
        $service->notify($broadcast, LocaleAllowedEnum::DE, new TranslationFailedException('boom'));
    }

    public function testFailedEmailSendIsCaughtAndLoggedNotRethrown(): void
    {
        $admin = new User();
        $admin->email = 'admin@test.com';
        $admin->locale = 'fr';

        $broadcast = new ContactBroadcast();
        $broadcast->subject = 'Annonce globale';

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findOneByEmail')->willReturn($admin);

        $translator = $this->createStub(TranslatorInterface::class);

        $emailService = $this->createStub(EmailInterface::class);
        $emailService->method('createEmail')->willReturn(new TemplatedEmail());
        $emailService->method('sendEmail')->willThrowException(new TransportException('SMTP down.'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $service = new BroadcastTranslationFailureNotifierService($emailService, $userRepository, $translator, $logger, 'admin@test.com');
        $service->notify($broadcast, LocaleAllowedEnum::DE, new TranslationFailedException('boom'));
    }
}
