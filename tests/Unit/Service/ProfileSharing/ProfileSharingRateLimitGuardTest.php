<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Entity\User;
use App\Exception\ProfileSharing\TooManyProfileSharingAttemptsException;
use App\Service\ProfileSharing\ProfileSharingRateLimitGuard;
use App\Service\Security\RateLimiterService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Uid\Uuid;

final class ProfileSharingRateLimitGuardTest extends TestCase
{
    private const int LIMIT = 2;

    private RateLimiterFactory $factory;

    private ProfileSharingRateLimitGuard $guard;

    protected function setUp(): void
    {
        $this->factory = new RateLimiterFactory([
            'id' => 'profile_sharing_test',
            'policy' => 'sliding_window',
            'limit' => self::LIMIT,
            'interval' => '15 minutes',
        ], new InMemoryStorage());

        $this->guard = new ProfileSharingRateLimitGuard(new RateLimiterService());
    }

    public function testAttemptsWithinTheLimitAreAccepted(): void
    {
        $user = $this->createUser();

        for ($attempt = 0; self::LIMIT > $attempt; ++$attempt) {
            $this->guard->consumeOrFail($this->factory, $user);
        }

        $this->addToAssertionCount(1);
    }

    public function testAttemptBeyondTheLimitThrowsWithTheMinutesToWait(): void
    {
        $user = $this->createUser();

        for ($attempt = 0; self::LIMIT > $attempt; ++$attempt) {
            $this->guard->consumeOrFail($this->factory, $user);
        }

        try {
            $this->guard->consumeOrFail($this->factory, $user);
            self::fail('Expected the rate limit to be exceeded.');
        } catch (TooManyProfileSharingAttemptsException $exception) {
            self::assertGreaterThanOrEqual(1, $exception->retryAfterMinutes);
        }
    }

    public function testLimitIsTrackedPerUser(): void
    {
        $exhaustedUser = $this->createUser();
        $otherUser = $this->createUser();

        for ($attempt = 0; self::LIMIT > $attempt; ++$attempt) {
            $this->guard->consumeOrFail($this->factory, $exhaustedUser);
        }

        $this->guard->consumeOrFail($this->factory, $otherUser);

        $this->addToAssertionCount(1);
    }

    public function testUserWithoutIdentifierIsRejected(): void
    {
        $this->expectException(\LogicException::class);

        $this->guard->consumeOrFail($this->factory, new User());
    }

    private function createUser(): User
    {
        $user = new User();
        $user->id = Uuid::v4();

        return $user;
    }
}
