<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Entity\User;
use App\Repository\ProfileConnectionRepository;
use App\Twig\Extension\ProfileConnectionPendingCountExtension;
use PHPUnit\Framework\TestCase;

final class ProfileConnectionPendingCountExtensionTest extends TestCase
{
    public function testReturnsTheNumberOfRequestsAwaitingTheUsersAnswer(): void
    {
        $user = new User();

        $repository = $this->createMock(ProfileConnectionRepository::class);
        $repository->expects(self::once())->method('countPendingReceivedBy')->with($user)->willReturn(3);

        self::assertSame(3, new ProfileConnectionPendingCountExtension($repository)->pendingCount($user));
    }
}
