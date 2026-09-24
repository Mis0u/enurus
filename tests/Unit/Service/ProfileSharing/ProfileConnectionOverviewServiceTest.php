<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Repository\ProfileConnectionRepository;
use App\Service\ProfileSharing\ProfileConnectionActivityDetector;
use App\Service\ProfileSharing\ProfileConnectionOverviewService;
use PHPUnit\Framework\TestCase;

final class ProfileConnectionOverviewServiceTest extends TestCase
{
    use CreatesProfileSharingUsersTrait;

    public function testGroupsConnectionsByTheRoleOfTheUser(): void
    {
        $me = $this->createSearchableUser('Me', 'M3M3M3');
        $sender = $this->createSearchableUser('Sender', 'S3ND3R');
        $target = $this->createSearchableUser('Target', 'T4RG3T');
        $friend = $this->createSearchableUser('Friend', 'F5R13N');

        $received = $this->createConnection($sender, $me, ProfileConnectionStatusEnum::PENDING);
        $sent = $this->createConnection($me, $target, ProfileConnectionStatusEnum::PENDING);
        $accepted = $this->createConnection($me, $friend, ProfileConnectionStatusEnum::ACCEPTED);

        $overview = $this->createService([$received, $sent, $accepted])->forUser($me);

        self::assertCount(1, $overview->receivedRequests);
        self::assertSame($received, $overview->receivedRequests[0]->connection);
        self::assertSame($sender, $overview->receivedRequests[0]->counterpart);
        self::assertCount(1, $overview->sentRequests);
        self::assertSame($target, $overview->sentRequests[0]->counterpart);
        self::assertCount(1, $overview->connections);
        self::assertSame($friend, $overview->connections[0]->counterpart);
    }

    public function testAcceptedConnectionShowsTheOtherPartyWhicheverSideTheUserIsOn(): void
    {
        $me = $this->createSearchableUser('Me', 'M3M3M3');
        $friend = $this->createSearchableUser('Friend', 'F5R13N');

        $overview = $this->createService([$this->createConnection($friend, $me, ProfileConnectionStatusEnum::ACCEPTED)])->forUser($me);

        self::assertSame($friend, $overview->connections[0]->counterpart);
    }

    public function testAcceptedConnectionsAreSortedByNicknameIgnoringCase(): void
    {
        $me = $this->createSearchableUser('Me', 'M3M3M3');
        $zoe = $this->createSearchableUser('zoe', 'Z0E2Z2');
        $alice = $this->createSearchableUser('Alice', 'A1C3A4');
        $bob = $this->createSearchableUser('bob', 'B0B5B6');

        $overview = $this->createService([
            $this->createConnection($me, $zoe, ProfileConnectionStatusEnum::ACCEPTED),
            $this->createConnection($me, $alice, ProfileConnectionStatusEnum::ACCEPTED),
            $this->createConnection($bob, $me, ProfileConnectionStatusEnum::ACCEPTED),
        ])->forUser($me);

        $nicknames = array_map(static fn ($entry): string => $entry->counterpart->nickname, $overview->connections);

        self::assertSame(['Alice', 'bob', 'zoe'], $nicknames);
    }

    public function testEndedConnectionsAreNotListed(): void
    {
        $me = $this->createSearchableUser('Me', 'M3M3M3');
        $other = $this->createSearchableUser('Other', 'O7H3R8');

        $overview = $this->createService([
            $this->createConnection($me, $other, ProfileConnectionStatusEnum::DECLINED),
            $this->createConnection($other, $me, ProfileConnectionStatusEnum::REVOKED),
        ])->forUser($me);

        self::assertTrue($overview->isEmpty);
    }

    public function testAcceptedConnectionsAreFlaggedWhenTheCounterpartHasANewWorkout(): void
    {
        $me = $this->createSearchableUser('Me', 'M3M3M3');
        $active = $this->createConnection($me, $this->createSearchableUser('Active', 'A1C7V3'), ProfileConnectionStatusEnum::ACCEPTED);
        $quiet = $this->createConnection($me, $this->createSearchableUser('Quiet', 'Q1U3T4'), ProfileConnectionStatusEnum::ACCEPTED);

        $overview = $this->createService([$active, $quiet], withNewWorkout: [$active])->forUser($me);

        self::assertTrue($overview->connections[0]->hasNewWorkout);
        self::assertFalse($overview->connections[1]->hasNewWorkout);
    }

    public function testPendingRequestsAreNeverFlagged(): void
    {
        $me = $this->createSearchableUser('Me', 'M3M3M3');
        $received = $this->createConnection($this->createSearchableUser('Sender', 'S3ND3R'), $me, ProfileConnectionStatusEnum::PENDING);

        $overview = $this->createService([$received], withNewWorkout: [$received])->forUser($me);

        self::assertFalse($overview->receivedRequests[0]->hasNewWorkout);
    }

    public function testOverviewIsEmptyWithoutAnyConnection(): void
    {
        self::assertTrue($this->createService([])->forUser($this->createSearchableUser())->isEmpty);
    }

    /**
     * @param list<ProfileConnection> $connections
     * @param list<ProfileConnection> $withNewWorkout
     */
    private function createService(array $connections, array $withNewWorkout = []): ProfileConnectionOverviewService
    {
        $repository = $this->createStub(ProfileConnectionRepository::class);
        $repository->method('findActiveInvolving')->willReturn($connections);

        $activityDetector = $this->createStub(ProfileConnectionActivityDetector::class);
        $activityDetector->method('findWithNewWorkout')->willReturnCallback(
            static fn (User $viewer, array $candidates): array => array_values(array_filter(
                $withNewWorkout,
                static fn (ProfileConnection $connection): bool => \in_array($connection, $candidates, true),
            )),
        );

        return new ProfileConnectionOverviewService($repository, $activityDetector);
    }

    private function createConnection(User $requester, User $addressee, ProfileConnectionStatusEnum $status): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->requester = $requester;
        $connection->addressee = $addressee;
        $connection->status = $status;

        return $connection;
    }
}
