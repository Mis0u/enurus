<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\User;
use App\Exception\ProfileSharing\TooManyProfileSharingAttemptsException;
use App\Service\Security\RateLimiterService;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Le compteur est propre à chaque utilisateur (pas à l'IP) : la recherche et la demande exigent
 * d'être connecté, et l'IP se partage (réseau mobile, salle de sport).
 */
final readonly class ProfileSharingRateLimitGuard
{
    public function __construct(
        private RateLimiterService $rateLimiterService
    ) {
    }

    public function consumeOrFail(RateLimiterFactoryInterface $limiterFactory, User $user): void
    {
        $userId = $user->id?->toRfc4122() ?? throw new \LogicException('User must be persisted to be rate limited.');

        $result = $this->rateLimiterService->checkLimit($limiterFactory, $userId);

        if (! $result['accepted']) {
            throw new TooManyProfileSharingAttemptsException((int) $result['minutes']);
        }
    }
}
