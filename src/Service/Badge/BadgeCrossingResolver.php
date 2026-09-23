<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Entity\User;
use App\Repository\WorkoutTonnageRepository;
use App\Service\Workout\DeloadPeriodSetService;
use Symfony\Component\Clock\ClockInterface;

/**
 * Charge l'historique d'un utilisateur une seule fois et date chaque palier demandé via
 * `BadgeCrossingLocator`. Non `final` : stubbé par `BadgeSyncServiceTest`.
 */
readonly class BadgeCrossingResolver
{
    public function __construct(
        private WorkoutTonnageRepository $workoutTonnageRepository,
        private DeloadPeriodSetService $deloadPeriodSetService,
        private BadgeCrossingLocator $crossingLocator,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, BadgeKey> $keys clé = `BadgeKey::id()`
     * @return array<string, BadgeCrossing> clé = `BadgeKey::id()`, absente si introuvable
     */
    public function resolve(User $user, array $keys): array
    {
        $timeline = array_map(
            static fn (array $row): WorkoutTimelineEntry => new WorkoutTimelineEntry($row['id'], $row['performedAt'], $row['tonnage']),
            $this->workoutTonnageRepository->findTimelineByUser($user),
        );
        $deloadWeekSet = $this->deloadPeriodSetService->weekKeySet($user);
        $now = $this->clock->now();

        $crossings = [];

        foreach ($keys as $id => $key) {
            $crossing = $this->crossingLocator->locate($key, $timeline, $deloadWeekSet, $user->createdAt, $now);

            if (null !== $crossing) {
                $crossings[$id] = $crossing;
            }
        }

        return $crossings;
    }
}
