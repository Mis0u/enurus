<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Repository\WorkoutStatsRepository;
use App\Security\Voter\ProfileConnectionVoter;
use App\Service\ProfileSharing\ProfileConnectionActivityDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProfileConnectionActivityDetectorTest extends TestCase
{
    use CreatesProfileSharingUsersTrait;

    private const string LAST_VISIT = '2026-09-20 10:00:00';

    public function testFlagsOnlyConnectionsWhoseCounterpartCreatedAWorkoutSinceTheLastVisit(): void
    {
        $me = $this->createSearchableUser('Me', 'M3M3M3');
        $active = $this->createConnectionSeenByMe($me, $this->createSearchableUser('Active', 'A1C7V3'));
        $quiet = $this->createConnectionSeenByMe($me, $this->createSearchableUser('Quiet', 'Q1U3T4'));

        $flagged = $this->createDetector([(string) $active->counterpartOf($me)->id])->findWithNewWorkout($me, [$active, $quiet]);

        self::assertSame([$active], $flagged);
    }

    public function testAsksForWorkoutsCreatedSinceTheViewersOwnLastVisit(): void
    {
        $me = $this->createSearchableUser('Me', 'M3M3M3');
        $friend = $this->createSearchableUser('Friend', 'F5R13N');
        $connection = $this->createConnectionSeenByMe($me, $friend);
        $connection->markSeenBy($friend, new \DateTimeImmutable('2026-09-23 08:00:00'));

        $repository = $this->createMock(WorkoutStatsRepository::class);
        $repository->expects(self::once())
            ->method('findOwnerIdsWithWorkoutCreatedSince')
            ->with([
                (string) $friend->id => new \DateTimeImmutable(self::LAST_VISIT),
            ])
            ->willReturn([]);

        (new ProfileConnectionActivityDetector($repository, $this->createAuthorizationChecker(true)))->findWithNewWorkout($me, [$connection]);
    }

    public function testNeverFlagsAConnectionTheViewerIsNotAllowedToSee(): void
    {
        $me = $this->createSearchableUser('Me', 'M3M3M3');
        $connection = $this->createConnectionSeenByMe($me, $this->createSearchableUser('Hidden', 'H1DD3N'));

        $flagged = $this->createDetector([(string) $connection->counterpartOf($me)->id], canView: false)->findWithNewWorkout($me, [$connection]);

        self::assertSame([], $flagged);
    }

    public function testNeverFlagsAConnectionNeverVisited(): void
    {
        $me = $this->createSearchableUser('Me', 'M3M3M3');
        $friend = $this->createSearchableUser('Friend', 'F5R13N');
        $connection = $this->createConnection($me, $friend);

        $flagged = $this->createDetector([(string) $friend->id])->findWithNewWorkout($me, [$connection]);

        self::assertSame([], $flagged);
    }

    /**
     * @param list<string> $ownerIdsWithNewWorkout
     */
    private function createDetector(array $ownerIdsWithNewWorkout, bool $canView = true): ProfileConnectionActivityDetector
    {
        $repository = $this->createStub(WorkoutStatsRepository::class);
        $repository->method('findOwnerIdsWithWorkoutCreatedSince')->willReturn($ownerIdsWithNewWorkout);

        return new ProfileConnectionActivityDetector($repository, $this->createAuthorizationChecker($canView));
    }

    private function createAuthorizationChecker(bool $canView): AuthorizationCheckerInterface
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => ProfileConnectionVoter::VIEW === $attribute && $canView,
        );

        return $authorizationChecker;
    }

    private function createConnectionSeenByMe(User $me, User $counterpart): ProfileConnection
    {
        $connection = $this->createConnection($me, $counterpart);
        $connection->markSeenBy($me, new \DateTimeImmutable(self::LAST_VISIT));

        return $connection;
    }

    private function createConnection(User $me, User $counterpart): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->requester = $me;
        $connection->addressee = $counterpart;
        $connection->status = ProfileConnectionStatusEnum::ACCEPTED;

        return $connection;
    }
}
