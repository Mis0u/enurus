<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Repository\ProfileConnectionRepository;

final readonly class ProfileConnectionOverviewService
{
    public function __construct(
        private ProfileConnectionRepository $connectionRepository,
    ) {
    }

    public function forUser(User $user): ProfileConnectionOverview
    {
        $receivedRequests = [];
        $sentRequests = [];
        $connections = [];

        foreach ($this->connectionRepository->findActiveInvolving($user) as $connection) {
            $entry = new ProfileConnectionEntry($connection, $connection->counterpartOf($user));

            match (true) {
                ProfileConnectionStatusEnum::ACCEPTED === $connection->status => $connections[] = $entry,
                ProfileConnectionStatusEnum::PENDING === $connection->status && $connection->addressee === $user => $receivedRequests[] = $entry,
                ProfileConnectionStatusEnum::PENDING === $connection->status => $sentRequests[] = $entry,
                default => null,
            };
        }

        usort(
            $connections,
            static fn (ProfileConnectionEntry $a, ProfileConnectionEntry $b): int => strcmp(
                mb_strtolower($a->counterpart->nickname),
                mb_strtolower($b->counterpart->nickname),
            ),
        );

        return new ProfileConnectionOverview($receivedRequests, $sentRequests, $connections);
    }
}
