<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\BlockedUserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class BlockedUserCheckerTest extends TestCase
{
    private BlockedUserChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new BlockedUserChecker();
    }

    public function testCheckPreAuthAllowsNonBlockedVerifiedUser(): void
    {
        $user = new User();
        $user->email = 'not-blocked@test.com';
        $user->isVerified = true;

        $this->checker->checkPreAuth($user);

        $this->addToAssertionCount(1);
    }

    public function testCheckPreAuthRejectsBlockedUser(): void
    {
        $user = new User();
        $user->email = 'blocked@test.com';
        $user->isVerified = true;
        $user->accountBlockedAt = new \DateTimeImmutable();

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('account_blocked');

        $this->checker->checkPreAuth($user);
    }

    public function testCheckPreAuthRejectsUnverifiedUser(): void
    {
        $user = new User();
        $user->email = 'unverified@test.com';

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('account_not_verified');

        $this->checker->checkPreAuth($user);
    }

    public function testCheckPostAuthNeverThrows(): void
    {
        $user = new User();
        $user->email = 'blocked@test.com';
        $user->isVerified = true;
        $user->accountBlockedAt = new \DateTimeImmutable();

        $this->checker->checkPostAuth($user);

        $this->addToAssertionCount(1);
    }
}
