<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Service\Email\EmailInterface;
use App\Service\ProfileSharing\InvitationAcceptedNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

final class InvitationAcceptedNotifierTest extends TestCase
{
    use CreatesProfileSharingUsersTrait;

    private const string TEMPLATE = 'emails/profile_connection_invitation_accepted.html.twig';

    public function testEmailsTheInviterInTheirLanguage(): void
    {
        $connection = $this->createConnection(inviterWantsEmails: true);
        $connection->requester->locale = 'de';

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::once())
            ->method('createEmail')
            ->with(
                'inviter@test.com',
                'profile_connection.email.invitation_accepted.subject',
                [
                    'inviteeNickname' => 'Invitee',
                    'locale' => 'de',
                ],
                self::TEMPLATE,
                'de',
            )
            ->willReturn(new TemplatedEmail());
        $emailService->expects(self::once())->method('sendEmail');

        $this->createNotifier($emailService)->notify($connection);
    }

    public function testSendsNothingWhenTheInviterTurnedConnectionEmailsOff(): void
    {
        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::never())->method('createEmail');
        $emailService->expects(self::never())->method('sendEmail');

        $this->createNotifier($emailService)->notify($this->createConnection(inviterWantsEmails: false));
    }

    private function createConnection(bool $inviterWantsEmails): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->requester = $this->createSearchableUser('Inviter', 'B8L3YN');
        $connection->requester->emailOnConnectionRequest = $inviterWantsEmails;
        $connection->addressee = $this->createSearchableUser('Invitee', 'A7K2XM');

        return $connection;
    }

    private function createNotifier(EmailInterface $emailService): InvitationAcceptedNotifier
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new InvitationAcceptedNotifier($emailService, $translator, new NullLogger());
    }
}
