<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Service\Security\RateLimiterService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class RateLimiterServiceTest extends TestCase
{
    private const string KEY = 'user-fixture-0';

    private RateLimiterFactory $factory;

    private RateLimiterService $rateLimiterService;

    protected function setUp(): void
    {
        $storage = new InMemoryStorage();
        $this->factory = new RateLimiterFactory([
            'id' => 'password_change',
            'policy' => 'sliding_window',
            'limit' => 5,
            'interval' => '15 minutes',
        ], $storage);

        $this->rateLimiterService = new RateLimiterService();
    }

    public function testFiveAttemptsAreAccepted(): void
    {
        for ($i = 0; 5 > $i; $i++) {
            $result = $this->rateLimiterService->checkLimit($this->factory, self::KEY);
            self::assertTrue($result['accepted']);
            self::assertSame('0', $result['minutes']);
        }
    }

    public function testSixthAttemptIsRejectedWithMinutesRemaining(): void
    {
        for ($i = 0; 5 > $i; $i++) {
            $this->rateLimiterService->checkLimit($this->factory, self::KEY);
        }

        $result = $this->rateLimiterService->checkLimit($this->factory, self::KEY);

        self::assertFalse($result['accepted']);
        self::assertGreaterThanOrEqual(1, (int) $result['minutes']);
    }
}
