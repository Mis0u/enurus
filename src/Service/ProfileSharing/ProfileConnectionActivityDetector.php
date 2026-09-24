<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Repository\WorkoutStatsRepository;
use App\Security\Voter\ProfileConnectionVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Connexions dont l'autre partie a créé une séance depuis la dernière visite de `$viewer` sur ses
 * pages. Une connexion que `$viewer` n'a pas le droit de voir (partage coupé d'un côté ou de
 * l'autre) n'est jamais signalée : ce serait révéler son activité par un autre chemin.
 */
readonly class ProfileConnectionActivityDetector
{
    public function __construct(
        private WorkoutStatsRepository $workoutStatsRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * @param list<ProfileConnection> $connections
     * @return list<ProfileConnection>
     */
    public function findWithNewWorkout(User $viewer, array $connections): array
    {
        $lastVisits = $this->lastVisitByCounterpartId($viewer, $connections);
        $ownerIdsWithNewWorkout = array_intersect(
            $this->workoutStatsRepository->findOwnerIdsWithWorkoutCreatedSince($lastVisits),
            array_keys($lastVisits),
        );

        return array_values(array_filter(
            $connections,
            static fn (ProfileConnection $connection): bool => \in_array((string) $connection->counterpartOf($viewer)->id, $ownerIdsWithNewWorkout, true),
        ));
    }

    /**
     * @param list<ProfileConnection> $connections
     * @return array<string, \DateTimeImmutable>
     */
    private function lastVisitByCounterpartId(User $viewer, array $connections): array
    {
        $lastVisits = [];

        foreach ($connections as $connection) {
            $lastVisit = $connection->lastSeenBy($viewer);

            if (null !== $lastVisit && $this->authorizationChecker->isGranted(ProfileConnectionVoter::VIEW, $connection)) {
                $lastVisits[(string) $connection->counterpartOf($viewer)->id] = $lastVisit;
            }
        }

        return $lastVisits;
    }
}
