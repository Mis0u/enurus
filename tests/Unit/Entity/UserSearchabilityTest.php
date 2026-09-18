<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserSearchabilityTest extends TestCase
{
    public function testDiscoverableVerifiedUserIsSearchable(): void
    {
        self::assertTrue($this->createSearchableUser()->isSearchable);
    }

    public function testUserWhoDidNotOptInIsNotSearchable(): void
    {
        $user = $this->createSearchableUser();
        $user->isDiscoverable = false;

        self::assertFalse($user->isSearchable);
    }

    public function testUnverifiedUserIsNotSearchable(): void
    {
        $user = $this->createSearchableUser();
        $user->isVerified = false;

        self::assertFalse($user->isSearchable);
    }

    public function testBlockedUserIsNotSearchable(): void
    {
        $user = $this->createSearchableUser();
        $user->accountBlockedAt = new \DateTimeImmutable();

        self::assertFalse($user->isSearchable);
    }

    public function testUserPendingDeletionIsNotSearchable(): void
    {
        $user = $this->createSearchableUser();
        $user->deletionRequestedAt = new \DateTimeImmutable();

        self::assertFalse($user->isSearchable);
    }

    private function createSearchableUser(): User
    {
        $user = new User();
        $user->isVerified = true;
        $user->isDiscoverable = true;

        return $user;
    }
}
