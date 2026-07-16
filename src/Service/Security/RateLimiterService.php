<?php

declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final readonly class RateLimiterService
{
    private const int HOUR_IN_MINUTE = 60;

    /**
     * @return array{accepted: bool, minutes: string}
     */
    public function checkLimit(RateLimiterFactoryInterface $rateLimiterFactory, string $key): array
    {
        $limiter = $rateLimiterFactory->create($key);
        $limit = $limiter->consume();

        if ($limit->isAccepted()) {
            return [
                'accepted' => true,
                'minutes' => '0',
            ];
        }

        $leftTime = $limit->getRetryAfter()->getTimestamp() - time();
        $minutes = max(1, ceil($leftTime / self::HOUR_IN_MINUTE));

        return [
            'accepted' => false,
            'minutes' => (string) $minutes,
        ];
    }
}
