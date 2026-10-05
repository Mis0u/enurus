<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserSerializationTest extends TestCase
{
    public function testTheSessionOnlyKeepsAChecksumOfThePasswordHash(): void
    {
        $user = new User();
        $user->email = 'user@test.com';
        $user->password = 'password-hash';

        $serialized = serialize($user);

        self::assertStringNotContainsString('password-hash', $serialized);
        self::assertSame(hash('crc32c', 'password-hash'), $this->unserializeUser($serialized)->password);
    }

    private function unserializeUser(string $serialized): User
    {
        $user = unserialize($serialized, [
            'allowed_classes' => true,
        ]);

        return $user instanceof User ? $user : throw new \LogicException('A user was expected.');
    }
}
