<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Service\Email\EmailInterface;
use App\Service\ProfileSharing\ProfileConnectionRequestNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProfileConnectionRequestNotifierTest extends TestCase
{
    use CreatesProfileSharingUsersTrait;

    private const string TEMPLATE = 'emails/profile_connection_request.html.twig';

    public function testEmailsTheAddresseeInTheirLanguage(): void
    {
        $connection = $this->createConnection(addresseeWantsEmails: true);
        $connection->addressee->locale = 'de';

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::once())
            ->method('createEmail')
            ->with(
                'addressee@test.com',
                'profile_connection.email.request.subject',
                [
                    'requesterNickname' => 'Requester',
                    'locale' => 'de',
                ],
                self::TEMPLATE,
                'de',
            )
            ->willReturn(new TemplatedEmail());
        $emailService->expects(self::once())->method('sendEmail');

        $this->createNotifier($emailService)->notify($connection);
    }

    public function testSendsNothingWhenTheAddresseeTurnedTheseEmailsOff(): void
    {
        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::never())->method('createEmail');
        $emailService->expects(self::never())->method('sendEmail');

        $this->createNotifier($emailService)->notify($this->createConnection(addresseeWantsEmails: false));
    }

    private function createConnection(bool $addresseeWantsEmails): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->requester = $this->createSearchableUser('Requester', 'B8L3YN');
        $connection->addressee = $this->createSearchableUser('Addressee', 'A7K2XM');
        $connection->addressee->emailOnConnectionRequest = $addresseeWantsEmails;

        return $connection;
    }

    private function createNotifier(EmailInterface $emailService): ProfileConnectionRequestNotifier
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ProfileConnectionRequestNotifier($emailService, $translator, new NullLogger());
    }
}
