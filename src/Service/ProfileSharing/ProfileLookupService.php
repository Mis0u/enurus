<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Toute cause d'échec (saisie invalide, code inconnu, pseudo qui ne correspond pas, profil non
 * trouvable, soi-même) renvoie `null` de façon indistinguable : la réponse ne doit jamais révéler
 * si un code existe. Le compteur est consommé avant même de valider la saisie, sinon des requêtes
 * mal formées permettraient de contourner la limite.
 */
final readonly class ProfileLookupService
{
    public function __construct(
        private UserRepository $userRepository,
        private ProfileSharingRateLimitGuard $rateLimitGuard,
        private RateLimiterFactoryInterface $profileSearchLimiter,
    ) {
    }

    public function find(User $searcher, string $query): ?User
    {
        $this->rateLimitGuard->consumeOrFail($this->profileSearchLimiter, $searcher);

        $shareCodeQuery = ShareCodeQuery::tryFromString($query);

        if (null === $shareCodeQuery) {
            return null;
        }

        $candidate = $this->userRepository->findOneByShareCode($shareCodeQuery->shareCode);

        if (null === $candidate || ! $this->matches($candidate, $shareCodeQuery, $searcher)) {
            return null;
        }

        return $candidate;
    }

    private function matches(User $candidate, ShareCodeQuery $query, User $searcher): bool
    {
        return $candidate->isSearchable
            && ! $this->isSameUser($candidate, $searcher)
            && mb_strtolower($candidate->nickname) === mb_strtolower($query->nickname);
    }

    private function isSameUser(User $first, User $second): bool
    {
        return $first->id?->equals($second->id) ?? false;
    }
}
