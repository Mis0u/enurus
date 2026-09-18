<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Exception\ProfileSharing\ProfileConnectionException;
use App\Exception\ProfileSharing\ProfileConnectionFailureReasonEnum;
use App\Repository\ProfileConnectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Une seule ligne par paire d'utilisateurs, quel que soit le sens : une connexion refusée ou
 * révoquée est rouverte (la ligne est réutilisée, les rôles éventuellement inversés) plutôt
 * qu'une seconde ligne créée — d'où l'absence de contrainte sur la paire non ordonnée en base.
 * Le délai avant une nouvelle demande évite de relancer en boucle quelqu'un qui a refusé.
 *
 * `$addressee` vient d'un identifiant transmis par le client : la recherche n'est pas le seul
 * chemin possible, d'où la revérification de `isSearchable` ici.
 */
final readonly class ProfileConnectionRequestService
{
    public const int RE_REQUEST_COOLDOWN_DAYS = 30;

    public function __construct(
        private ProfileConnectionRepository $connectionRepository,
        private EntityManagerInterface $entityManager,
        private ProfileSharingRateLimitGuard $rateLimitGuard,
        private RateLimiterFactoryInterface $profileConnectionRequestLimiter,
        private ClockInterface $clock,
    ) {
    }

    public function request(User $requester, User $addressee): ProfileConnection
    {
        $this->rateLimitGuard->consumeOrFail($this->profileConnectionRequestLimiter, $requester);
        $this->assertCanBeRequested($requester, $addressee);

        $existing = $this->connectionRepository->findBetween($requester, $addressee);

        if (null === $existing) {
            return $this->create($requester, $addressee);
        }

        return $this->reopen($existing, $requester, $addressee);
    }

    private function assertCanBeRequested(User $requester, User $addressee): void
    {
        if ($requester->id?->equals($addressee->id)) {
            throw new ProfileConnectionException(ProfileConnectionFailureReasonEnum::SELF_REQUEST);
        }

        if (! $addressee->isSearchable) {
            throw new ProfileConnectionException(ProfileConnectionFailureReasonEnum::NOT_SEARCHABLE);
        }
    }

    private function create(User $requester, User $addressee): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->requester = $requester;
        $connection->addressee = $addressee;

        $this->entityManager->persist($connection);
        $this->entityManager->flush();

        return $connection;
    }

    private function reopen(ProfileConnection $connection, User $requester, User $addressee): ProfileConnection
    {
        $this->assertReopenable($connection);

        $connection->requester = $requester;
        $connection->addressee = $addressee;
        $connection->status = ProfileConnectionStatusEnum::PENDING;
        $connection->respondedAt = null;

        $this->entityManager->flush();

        return $connection;
    }

    private function assertReopenable(ProfileConnection $connection): void
    {
        if (ProfileConnectionStatusEnum::PENDING === $connection->status) {
            throw new ProfileConnectionException(ProfileConnectionFailureReasonEnum::ALREADY_PENDING);
        }

        if (ProfileConnectionStatusEnum::ACCEPTED === $connection->status) {
            throw new ProfileConnectionException(ProfileConnectionFailureReasonEnum::ALREADY_CONNECTED);
        }

        $reopenableFrom = $connection->respondedAt?->modify(\sprintf('+%d days', self::RE_REQUEST_COOLDOWN_DAYS));

        if (null !== $reopenableFrom && $reopenableFrom > $this->clock->now()) {
            throw new ProfileConnectionException(ProfileConnectionFailureReasonEnum::COOLDOWN_ACTIVE);
        }
    }
}
