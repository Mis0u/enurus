<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Repository\UserRepository;
use App\Service\ProfileSharing\InvitationAcceptedNotifier;
use App\Service\ProfileSharing\InvitationConnectionService;
use App\Service\ProfileSharing\ShareCodeAssigner;
use App\Service\ProfileSharing\ShareCodeGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class InvitationConnectionServiceTest extends TestCase
{
    use CreatesProfileSharingUsersTrait;

    private const string NOW = '2026-10-05 12:00:00';

    public function testConnectsTheInviteeToTheInviterRightAway(): void
    {
        $inviter = $this->createSearchableUser('Inviter', 'B8L3YN');
        $invitee = $this->createInvitee($inviter);
        $persisted = [];

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            },
        );
        $entityManager->expects(self::once())->method('flush');

        self::assertSame($inviter, $this->createService($entityManager)->connect($invitee));

        self::assertCount(1, $persisted);
        $connection = $persisted[0];
        self::assertInstanceOf(ProfileConnection::class, $connection);
        self::assertSame($inviter, $connection->requester);
        self::assertSame($invitee, $connection->addressee);
        self::assertSame(ProfileConnectionStatusEnum::ACCEPTED, $connection->status);
        self::assertEquals(new \DateTimeImmutable(self::NOW), $connection->respondedAt);
        self::assertEquals(new \DateTimeImmutable(self::NOW), $connection->lastSeenBy($inviter));
        self::assertEquals(new \DateTimeImmutable(self::NOW), $connection->lastSeenBy($invitee));
    }

    public function testTurnsOnProfileSharingForTheInvitee(): void
    {
        $invitee = $this->createInvitee($this->createSearchableUser('Inviter', 'B8L3YN'));

        $this->createService($this->createStub(EntityManagerInterface::class))->connect($invitee);

        self::assertTrue($invitee->isDiscoverable);
        self::assertSame('NEW234', $invitee->shareCode);
    }

    public function testLetsTheInviterKnowTheirFriendJoined(): void
    {
        $invitee = $this->createInvitee($this->createSearchableUser('Inviter', 'B8L3YN'));

        $notifier = $this->createMock(InvitationAcceptedNotifier::class);
        $notifier->expects(self::once())
            ->method('notify')
            ->with(self::callback(
                static fn (ProfileConnection $connection): bool => $connection->addressee === $invitee,
            ));

        $this->createService($this->createStub(EntityManagerInterface::class), $notifier)->connect($invitee);
    }

    public function testDoesNothingWithoutAnInviter(): void
    {
        $invitee = $this->createInvitee(null);

        self::assertNull($this->createServiceExpectingNoConnection()->connect($invitee));
        self::assertFalse($invitee->isDiscoverable);
    }

    public function testDoesNothingWhenTheInviterStoppedSharingSinceTheInvitation(): void
    {
        $inviter = $this->createSearchableUser('Inviter', 'B8L3YN');
        $inviter->isDiscoverable = false;
        $invitee = $this->createInvitee($inviter);

        self::assertNull($this->createServiceExpectingNoConnection()->connect($invitee));
        self::assertFalse($invitee->isDiscoverable);
    }

    private function createInvitee(?User $inviter): User
    {
        $invitee = new User();
        $invitee->nickname = 'Invitee';
        $invitee->isVerified = true;
        $invitee->invitedBy = $inviter;

        return $invitee;
    }

    private function createServiceExpectingNoConnection(): InvitationConnectionService
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $notifier = $this->createMock(InvitationAcceptedNotifier::class);
        $notifier->expects(self::never())->method('notify');

        return $this->createService($entityManager, $notifier);
    }

    private function createService(
        EntityManagerInterface $entityManager,
        ?InvitationAcceptedNotifier $notifier = null,
    ): InvitationConnectionService {
        $generator = $this->createStub(ShareCodeGeneratorInterface::class);
        $generator->method('generate')->willReturn('NEW234');

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('existsByShareCode')->willReturn(false);

        return new InvitationConnectionService(
            $entityManager,
            new MockClock(self::NOW),
            new ShareCodeAssigner($generator, $userRepository),
            $notifier ?? $this->createStub(InvitationAcceptedNotifier::class),
        );
    }
}
