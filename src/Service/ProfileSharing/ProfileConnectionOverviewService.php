<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Repository\ProfileConnectionRepository;

final readonly class ProfileConnectionOverviewService
{
    public function __construct(
        private ProfileConnectionRepository $connectionRepository,
        private ProfileConnectionActivityDetector $activityDetector,
    ) {
    }

    public function forUser(User $user): ProfileConnectionOverview
    {
        $receivedRequests = [];
        $sentRequests = [];
        $accepted = [];

        foreach ($this->connectionRepository->findActiveInvolving($user) as $connection) {
            match (true) {
                ProfileConnectionStatusEnum::ACCEPTED === $connection->status => $accepted[] = $connection,
                ProfileConnectionStatusEnum::PENDING === $connection->status && $connection->addressee === $user => $receivedRequests[] = $this->createEntry($connection, $user),
                ProfileConnectionStatusEnum::PENDING === $connection->status => $sentRequests[] = $this->createEntry($connection, $user),
                default => null,
            };
        }

        return new ProfileConnectionOverview($receivedRequests, $sentRequests, $this->createConnectionEntries($accepted, $user));
    }

    /**
     * Connexions acceptées, triées par pseudo, chacune marquée si l'autre partie a créé une séance
     * depuis la dernière visite.
     *
     * @param list<ProfileConnection> $accepted
     * @return list<ProfileConnectionEntry>
     */
    private function createConnectionEntries(array $accepted, User $user): array
    {
        $withNewWorkout = $this->activityDetector->findWithNewWorkout($user, $accepted);

        $entries = array_map(
            fn (ProfileConnection $connection): ProfileConnectionEntry => $this->createEntry($connection, $user, \in_array($connection, $withNewWorkout, true)),
            $accepted,
        );

        usort(
            $entries,
            static fn (ProfileConnectionEntry $a, ProfileConnectionEntry $b): int => strcmp(
                mb_strtolower($a->counterpart->nickname),
                mb_strtolower($b->counterpart->nickname),
            ),
        );

        return $entries;
    }

    private function createEntry(ProfileConnection $connection, User $user, bool $hasNewWorkout = false): ProfileConnectionEntry
    {
        return new ProfileConnectionEntry($connection, $connection->counterpartOf($user), $hasNewWorkout);
    }
}
