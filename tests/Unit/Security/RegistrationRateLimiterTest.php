<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class RegistrationRateLimiterTest extends TestCase
{
    private const string IP_ADDRESS = '192.168.1.1';

    private RateLimiterFactory $factory;

    protected function setUp(): void
    {
        $storage = new InMemoryStorage();

        $this->factory = new RateLimiterFactory([
            'id' => 'registration',
            'policy' => 'sliding_window',
            'limit' => 3,
            'interval' => '15 minutes',
        ], $storage);
    }

    public function testThreeAttemptsAreAllowed(): void
    {
        $limiter = $this->factory->create(self::IP_ADDRESS);

        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertTrue($limiter->consume()->isAccepted());
    }

    public function testFourthAttemptIsBlocked(): void
    {
        $limiter = $this->factory->create(self::IP_ADDRESS);

        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertTrue($limiter->consume()->isAccepted());
        $this->assertTrue($limiter->consume()->isAccepted());

        $this->assertFalse($limiter->consume()->isAccepted());
    }
}
