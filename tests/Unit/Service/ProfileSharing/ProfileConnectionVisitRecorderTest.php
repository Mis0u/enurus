<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Service\ProfileSharing\ProfileConnectionVisitRecorder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ProfileConnectionVisitRecorderTest extends TestCase
{
    private const string NOW = '2026-09-24 12:00:00';

    public function testRecordsTheVisitOfTheViewerOnlyAtTheCurrentTime(): void
    {
        $connection = new ProfileConnection();
        $connection->requester = $viewer = new User();
        $connection->addressee = $visited = new User();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        (new ProfileConnectionVisitRecorder($entityManager, new MockClock(self::NOW)))->record($connection, $viewer);

        self::assertEquals(new \DateTimeImmutable(self::NOW), $connection->lastSeenBy($viewer));
        self::assertNull($connection->lastSeenBy($visited));
    }
}
