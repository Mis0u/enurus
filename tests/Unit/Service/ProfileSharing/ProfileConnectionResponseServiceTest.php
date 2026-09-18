<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Exception\ProfileSharing\ProfileConnectionException;
use App\Exception\ProfileSharing\ProfileConnectionFailureReasonEnum;
use App\Service\ProfileSharing\ProfileConnectionResponseService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ProfileConnectionResponseServiceTest extends TestCase
{
    private const string NOW = '2026-09-18 12:00:00';

    public function testAcceptingAPendingConnectionMarksItAcceptedAtTheCurrentTime(): void
    {
        $connection = $this->createConnection(ProfileConnectionStatusEnum::PENDING);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $this->createService($entityManager)->accept($connection);

        self::assertSame(ProfileConnectionStatusEnum::ACCEPTED, $connection->status);
        self::assertEquals(new \DateTimeImmutable(self::NOW), $connection->respondedAt);
    }

    public function testDecliningAPendingConnectionMarksItDeclinedAtTheCurrentTime(): void
    {
        $connection = $this->createConnection(ProfileConnectionStatusEnum::PENDING);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $this->createService($entityManager)->decline($connection);

        self::assertSame(ProfileConnectionStatusEnum::DECLINED, $connection->status);
        self::assertEquals(new \DateTimeImmutable(self::NOW), $connection->respondedAt);
    }

    public function testRevokingAnAcceptedConnectionMarksItRevokedAtTheCurrentTime(): void
    {
        $connection = $this->createConnection(ProfileConnectionStatusEnum::ACCEPTED);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $this->createService($entityManager)->revoke($connection);

        self::assertSame(ProfileConnectionStatusEnum::REVOKED, $connection->status);
        self::assertEquals(new \DateTimeImmutable(self::NOW), $connection->respondedAt);
    }

    public function testCancellingAPendingConnectionRemovesIt(): void
    {
        $connection = $this->createConnection(ProfileConnectionStatusEnum::PENDING);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($connection);
        $entityManager->expects(self::once())->method('flush');

        $this->createService($entityManager)->cancel($connection);
    }

    /**
     * @return iterable<string, array{string, ProfileConnectionStatusEnum}>
     */
    public static function transitionsFromTheWrongStatus(): iterable
    {
        foreach ([ProfileConnectionStatusEnum::ACCEPTED, ProfileConnectionStatusEnum::DECLINED, ProfileConnectionStatusEnum::REVOKED] as $status) {
            yield 'accept ' . $status->value => ['accept', $status];
            yield 'decline ' . $status->value => ['decline', $status];
            yield 'cancel ' . $status->value => ['cancel', $status];
        }

        foreach ([ProfileConnectionStatusEnum::PENDING, ProfileConnectionStatusEnum::DECLINED, ProfileConnectionStatusEnum::REVOKED] as $status) {
            yield 'revoke ' . $status->value => ['revoke', $status];
        }
    }

    #[DataProvider('transitionsFromTheWrongStatus')]
    public function testTransitionFromAnUnexpectedStatusIsRejectedWithoutChange(string $method, ProfileConnectionStatusEnum $status): void
    {
        $connection = $this->createConnection($status);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $entityManager->expects(self::never())->method('remove');

        try {
            $this->createService($entityManager)->{$method}($connection);
            self::fail('Expected the transition to be rejected.');
        } catch (ProfileConnectionException $exception) {
            self::assertSame(ProfileConnectionFailureReasonEnum::INVALID_TRANSITION, $exception->reason);
            self::assertSame($status, $connection->status);
        }
    }

    private function createConnection(ProfileConnectionStatusEnum $status): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->status = $status;

        return $connection;
    }

    private function createService(EntityManagerInterface $entityManager): ProfileConnectionResponseService
    {
        return new ProfileConnectionResponseService($entityManager, new MockClock(self::NOW));
    }
}
