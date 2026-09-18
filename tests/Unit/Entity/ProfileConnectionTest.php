<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ProfileConnection;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use PHPUnit\Framework\TestCase;

final class ProfileConnectionTest extends TestCase
{
    public function testNewConnectionIsPendingAndNotYetAnswered(): void
    {
        $connection = new ProfileConnection();

        self::assertSame(ProfileConnectionStatusEnum::PENDING, $connection->status);
        self::assertNull($connection->respondedAt);
    }
}
