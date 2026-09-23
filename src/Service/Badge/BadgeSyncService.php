<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Entity\User;
use App\Entity\UserBadge;
use App\Entity\Workout;
use App\Repository\UserBadgeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Seul point d'écriture des `UserBadge` : aligne les badges enregistrés sur ceux mérités
 * aujourd'hui. Appelé après toute action qui change l'historique de séances (création, édition,
 * suppression) et au chargement du dashboard (l'ancienneté progresse sans aucune séance).
 */
final readonly class BadgeSyncService
{
    public function __construct(
        private BadgeProgressCalculator $progressCalculator,
        private BadgeEligibilityResolver $eligibilityResolver,
        private BadgeCrossingResolver $crossingResolver,
        private UserBadgeRepository $userBadgeRepository,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param Workout|null $trigger séance qui vient d'être enregistrée — rattachée aux badges gagnés,
     *                              datés de maintenant. Sans séance déclenchante (chargement d'une
     *                              page, rattrapage d'un compte existant), chaque badge prend la date
     *                              et la séance qui ont réellement franchi le palier dans l'historique.
     */
    public function sync(User $user, ?Workout $trigger = null): BadgeSyncResult
    {
        $progress = $this->progressCalculator->calculate($user);
        $eligible = $this->eligibilityResolver->resolve($progress);
        $owned = $this->indexById($this->userBadgeRepository->findByOwner($user));

        $removedCount = $this->removeUndeserved($owned, $eligible);
        $missing = array_diff_key($eligible, $owned);
        $isRetroactive = [] === $owned && 1 < \count($missing);
        $gained = $this->unlockMissing($user, $missing, $isRetroactive ? null : $trigger);

        if ([] !== $gained || 0 < $removedCount) {
            $this->em->flush();
        }

        return new BadgeSyncResult($gained, $isRetroactive, $progress);
    }

    /**
     * @param array<string, UserBadge> $owned
     * @param array<string, BadgeKey> $eligible
     */
    private function removeUndeserved(array $owned, array $eligible): int
    {
        $removedCount = 0;

        foreach ($owned as $id => $badge) {
            if (! isset($eligible[$id]) && $badge->family->isRevocable()) {
                $this->em->remove($badge);
                $removedCount++;
            }
        }

        return $removedCount;
    }

    /**
     * @param array<string, BadgeKey> $missing
     * @return list<UserBadge>
     */
    private function unlockMissing(User $user, array $missing, ?Workout $trigger): array
    {
        $now = $this->clock->now();
        $crossings = null === $trigger && [] !== $missing ? $this->crossingResolver->resolve($user, $missing) : [];
        $gained = [];

        foreach ($missing as $id => $key) {
            $crossing = $crossings[$id] ?? null;
            $badge = UserBadge::unlock($user, $key, $crossing->date ?? $now, $trigger ?? $this->workoutOf($crossing));
            $this->em->persist($badge);
            $gained[] = $badge;
        }

        return $gained;
    }

    private function workoutOf(?BadgeCrossing $crossing): ?Workout
    {
        if (null === $crossing?->workoutId) {
            return null;
        }

        return $this->em->getReference(Workout::class, Uuid::fromString($crossing->workoutId));
    }

    /**
     * @param list<UserBadge> $badges
     * @return array<string, UserBadge>
     */
    private function indexById(array $badges): array
    {
        $indexed = [];

        foreach ($badges as $badge) {
            $indexed[$badge->key()->id()] = $badge;
        }

        return $indexed;
    }
}
