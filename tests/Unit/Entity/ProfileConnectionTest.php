<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ProfileConnection;
use App\Entity\User;
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

    public function testInvolvesBothPartiesButNoOneElse(): void
    {
        $connection = $this->createConnection($requester = new User(), $addressee = new User());

        self::assertTrue($connection->involves($requester));
        self::assertTrue($connection->involves($addressee));
        self::assertFalse($connection->involves(new User()));
    }

    public function testCounterpartOfEitherPartyIsTheOtherOne(): void
    {
        $connection = $this->createConnection($requester = new User(), $addressee = new User());

        self::assertSame($addressee, $connection->counterpartOf($requester));
        self::assertSame($requester, $connection->counterpartOf($addressee));
    }

    public function testCounterpartOfAStrangerIsRefused(): void
    {
        $connection = $this->createConnection(new User(), new User());

        $this->expectException(\LogicException::class);

        $connection->counterpartOf(new User());
    }

    private function createConnection(User $requester, User $addressee): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->requester = $requester;
        $connection->addressee = $addressee;

        return $connection;
    }
}
