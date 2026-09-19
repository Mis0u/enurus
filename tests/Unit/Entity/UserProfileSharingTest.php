<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserProfileSharingTest extends TestCase
{
    public function testNewUserIsNotDiscoverableAndHasNoShareCode(): void
    {
        $user = new User();

        self::assertFalse($user->isDiscoverable);
        self::assertNull($user->shareCode);
    }

    public function testNewUserHasNoProfileConnections(): void
    {
        $user = new User();

        self::assertCount(0, $user->sentProfileConnections);
        self::assertCount(0, $user->receivedProfileConnections);
    }
}
