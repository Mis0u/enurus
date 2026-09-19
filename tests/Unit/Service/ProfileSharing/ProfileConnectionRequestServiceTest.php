<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Exception\ProfileSharing\ProfileConnectionException;
use App\Exception\ProfileSharing\ProfileConnectionFailureReasonEnum;
use App\Exception\ProfileSharing\TooManyProfileSharingAttemptsException;
use App\Repository\ProfileConnectionRepository;
use App\Service\ProfileSharing\ProfileConnectionRequestService;
use App\Service\ProfileSharing\ProfileSharingRateLimitGuard;
use App\Service\Security\RateLimiterService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class ProfileConnectionRequestServiceTest extends TestCase
{
    use CreatesProfileSharingUsersTrait;

    private const string NOW = '2026-09-18 12:00:00';

    private const int REQUEST_LIMIT = 2;

    private MockClock $clock;

    private RateLimiterFactory $limiterFactory;

    protected function setUp(): void
    {
        $this->clock = new MockClock(self::NOW);
        $this->limiterFactory = new RateLimiterFactory([
            'id' => 'profile_connection_request_test',
            'policy' => 'sliding_window',
            'limit' => self::REQUEST_LIMIT,
            'interval' => '1 hour',
        ], new InMemoryStorage());
    }

    public function testCreatesAPendingConnectionFromRequesterToAddressee(): void
    {
        $requester = $this->createSearchableUser('Requester', 'B8L3YN');
        $addressee = $this->createSearchableUser('Addressee', 'A7K2XM');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(ProfileConnection::class));
        $entityManager->expects(self::once())->method('flush');

        $connection = $this->createService(null, $entityManager)->request($requester, $addressee);

        self::assertSame($requester, $connection->requester);
        self::assertSame($addressee, $connection->addressee);
        self::assertSame(ProfileConnectionStatusEnum::PENDING, $connection->status);
        self::assertNull($connection->respondedAt);
    }

    public function testRejectsARequestToOneself(): void
    {
        $user = $this->createSearchableUser();

        $this->assertRequestFailsWith(
            ProfileConnectionFailureReasonEnum::SELF_REQUEST,
            $this->createService(null),
            $user,
            $user,
        );
    }

    public function testRejectsARequesterWhoIsNotSharingTheirOwnProfile(): void
    {
        $requester = $this->createSearchableUser('Requester', 'B8L3YN');
        $requester->isDiscoverable = false;

        $this->assertRequestFailsWith(
            ProfileConnectionFailureReasonEnum::REQUESTER_NOT_SHARING,
            $this->createService(null),
            $requester,
            $this->createSearchableUser('Addressee', 'A7K2XM'),
        );
    }

    public function testRejectsAnAddresseeWhoCannotBeFound(): void
    {
        $addressee = $this->createSearchableUser('Addressee', 'A7K2XM');
        $addressee->isDiscoverable = false;

        $this->assertRequestFailsWith(
            ProfileConnectionFailureReasonEnum::NOT_SEARCHABLE,
            $this->createService(null),
            $this->createSearchableUser('Requester', 'B8L3YN'),
            $addressee,
        );
    }

    public function testRejectsARequestWhenOneIsAlreadyPending(): void
    {
        [$requester, $addressee] = $this->createPair();

        $this->assertRequestFailsWith(
            ProfileConnectionFailureReasonEnum::ALREADY_PENDING,
            $this->createService($this->createConnection($addressee, $requester, ProfileConnectionStatusEnum::PENDING)),
            $requester,
            $addressee,
        );
    }

    public function testRejectsARequestWhenAlreadyConnected(): void
    {
        [$requester, $addressee] = $this->createPair();

        $this->assertRequestFailsWith(
            ProfileConnectionFailureReasonEnum::ALREADY_CONNECTED,
            $this->createService($this->createConnection($requester, $addressee, ProfileConnectionStatusEnum::ACCEPTED)),
            $requester,
            $addressee,
        );
    }

    /**
     * @return iterable<string, array{ProfileConnectionStatusEnum}>
     */
    public static function endedStatuses(): iterable
    {
        yield 'declined' => [ProfileConnectionStatusEnum::DECLINED];
        yield 'revoked' => [ProfileConnectionStatusEnum::REVOKED];
    }

    #[DataProvider('endedStatuses')]
    public function testRejectsARequestDuringTheCooldownAfterAnEndedConnection(ProfileConnectionStatusEnum $endedStatus): void
    {
        [$requester, $addressee] = $this->createPair();
        $ended = $this->createConnection($requester, $addressee, $endedStatus);
        $ended->respondedAt = new \DateTimeImmutable(self::NOW)->modify('-29 days');

        $this->assertRequestFailsWith(
            ProfileConnectionFailureReasonEnum::COOLDOWN_ACTIVE,
            $this->createService($ended),
            $requester,
            $addressee,
        );
    }

    #[DataProvider('endedStatuses')]
    public function testReopensTheSameConnectionOnceTheCooldownHasElapsed(ProfileConnectionStatusEnum $endedStatus): void
    {
        [$firstRequester, $firstAddressee] = $this->createPair();
        $ended = $this->createConnection($firstRequester, $firstAddressee, $endedStatus);
        $ended->respondedAt = new \DateTimeImmutable(self::NOW)->modify('-30 days');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $connection = $this->createService($ended, $entityManager)->request($firstAddressee, $firstRequester);

        self::assertSame($ended, $connection);
        self::assertSame($firstAddressee, $connection->requester);
        self::assertSame($firstRequester, $connection->addressee);
        self::assertSame(ProfileConnectionStatusEnum::PENDING, $connection->status);
        self::assertNull($connection->respondedAt);
    }

    public function testRequestsBeyondTheLimitThrowAndPersistNothing(): void
    {
        $requester = $this->createSearchableUser('Requester', 'B8L3YN');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(self::REQUEST_LIMIT))->method('persist');
        $service = $this->createService(null, $entityManager);

        for ($request = 0; self::REQUEST_LIMIT > $request; ++$request) {
            $service->request($requester, $this->createSearchableUser('Target' . $request, 'C9M4ZP'));
        }

        $this->expectException(TooManyProfileSharingAttemptsException::class);

        $service->request($requester, $this->createSearchableUser('TargetLast', 'D2N5QR'));
    }

    /**
     * @return array{User, User}
     */
    private function createPair(): array
    {
        return [
            $this->createSearchableUser('Requester', 'B8L3YN'),
            $this->createSearchableUser('Addressee', 'A7K2XM'),
        ];
    }

    private function createConnection(User $requester, User $addressee, ProfileConnectionStatusEnum $status): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->requester = $requester;
        $connection->addressee = $addressee;
        $connection->status = $status;

        return $connection;
    }

    private function createService(?ProfileConnection $existingConnection, ?EntityManagerInterface $entityManager = null): ProfileConnectionRequestService
    {
        $repository = $this->createStub(ProfileConnectionRepository::class);
        $repository->method('findBetween')->willReturn($existingConnection);

        return new ProfileConnectionRequestService(
            $repository,
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            new ProfileSharingRateLimitGuard(new RateLimiterService()),
            $this->limiterFactory,
            $this->clock,
        );
    }

    private function assertRequestFailsWith(
        ProfileConnectionFailureReasonEnum $expectedReason,
        ProfileConnectionRequestService $service,
        User $requester,
        User $addressee,
    ): void {
        try {
            $service->request($requester, $addressee);
            self::fail('Expected the request to be rejected.');
        } catch (ProfileConnectionException $exception) {
            self::assertSame($expectedReason, $exception->reason);
        }
    }
}
