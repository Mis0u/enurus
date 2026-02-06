<?php

declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

readonly class RateLimiterService
{
    /**
     * @return array{accepted: bool, minutes: string}
     */
    public function checkLimit(RateLimiterFactoryInterface $rateLimiterFactory, string $ipAddress): array
    {
        $limiter = $rateLimiterFactory->create($ipAddress);
        $limit = $limiter->consume();

        if ($limit->isAccepted()) {
            return [
                'accepted' => true,
                'minutes' => '0',
            ];
        }

        $leftTime = $limit->getRetryAfter()->getTimestamp() - time();
        $minutes = max(1, ceil($leftTime / 60));

        return [
            'accepted' => false,
            'minutes' => (string) $minutes,
        ];
    }
}
