<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Entity\User;
use App\Entity\UserBadge;
use App\Entity\Workout;
use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Repository\UserBadgeRepository;
use App\Service\Badge\View\BadgeCollectionView;
use App\Service\Badge\View\BadgeFamilyView;
use App\Service\Badge\View\BadgeTileView;

/**
 * Prépare l'affichage des badges de `$subject` tel que le lit `$viewer` (langue, unité de poids)
 * — même principe que `DashboardViewDataBuilder`. Aucun calcul de progression ici : les valeurs
 * viennent de `BadgeSyncResult::$progress`, déjà calculées par la synchro qui précède toujours.
 */
final readonly class BadgeViewBuilder
{
    private const int LATEST_LIMIT = 3;

    private const int NEXT_LIMIT = 3;

    private const int FULL_PERCENT = 100;

    public function __construct(
        private UserBadgeRepository $userBadgeRepository,
        private BadgeLabelFormatter $labelFormatter,
    ) {
    }

    public function build(User $subject, User $viewer, BadgeProgress $progress): BadgeCollectionView
    {
        $owned = $this->indexById($this->userBadgeRepository->findByOwner($subject));
        $families = array_map(
            fn (BadgeFamilyEnum $family): BadgeFamilyView => $this->buildFamily($family, $owned, $progress, $viewer),
            BadgeFamilyEnum::milestoneFamilies(),
        );
        $legend = $this->buildTile(BadgeKey::legend(), $owned, $viewer);

        return new BadgeCollectionView(
            $families,
            $legend,
            $this->latest($families, $legend),
            $this->next($families),
            $this->rubyByFamily($families),
            \count($owned),
            \count(BadgeFamilyEnum::milestoneFamilies()) * \count(BadgeTierEnum::cases()) + 1,
        );
    }

    /**
     * Badges rattachés à une séance, pour sa page de détail.
     *
     * @return list<BadgeTileView>
     */
    public function buildForWorkout(Workout $workout, User $viewer): array
    {
        return $this->buildForWorkouts([(string) $workout->id], $viewer)[(string) $workout->id] ?? [];
    }

    /**
     * Badges rattachés à chaque séance d'une liste, en une seule requête.
     *
     * @param list<string> $workoutIds
     * @return array<string, list<BadgeTileView>> clé = id de la séance
     */
    public function buildForWorkouts(array $workoutIds, User $viewer): array
    {
        $tilesByWorkout = [];

        foreach ($this->userBadgeRepository->findByWorkoutIds($workoutIds) as $badge) {
            $tilesByWorkout[(string) $badge->workout?->id][] = $this->buildTile($badge->key(), [
                $badge->key()->id() => $badge,
            ], $viewer);
        }

        return $tilesByWorkout;
    }

    /**
     * @param array<string, UserBadge> $owned
     */
    private function buildFamily(BadgeFamilyEnum $family, array $owned, BadgeProgress $progress, User $viewer): BadgeFamilyView
    {
        $tiles = array_map(
            fn (BadgeTierEnum $tier): BadgeTileView => $this->buildTile(new BadgeKey($family, $tier), $owned, $viewer, $progress),
            BadgeTierEnum::cases(),
        );

        return new BadgeFamilyView($family, $tiles, $tiles[array_key_last($tiles)]->isEarned());
    }

    /**
     * @param array<string, UserBadge> $owned
     */
    private function buildTile(BadgeKey $key, array $owned, User $viewer, ?BadgeProgress $progress = null): BadgeTileView
    {
        $unlockedAt = ($owned[$key->id()] ?? null)?->unlockedAt;
        $showProgress = null === $unlockedAt && null !== $progress;

        return new BadgeTileView(
            $key->family,
            $key->tier,
            $this->labelFormatter->name($key, $viewer),
            $this->labelFormatter->ribbon($key, $viewer),
            $unlockedAt,
            $showProgress ? $this->labelFormatter->progress($key->family, $key->tier, $progress, $viewer) : null,
            $showProgress ? $this->percent($key, $progress) : 0,
        );
    }

    private function percent(BadgeKey $key, BadgeProgress $progress): int
    {
        $ratio = $progress->valueFor($key->family) / $key->family->thresholdOf($key->tier);

        return min(self::FULL_PERCENT, (int) floor($ratio * self::FULL_PERCENT));
    }

    /**
     * @param list<BadgeFamilyView> $families
     * @return list<BadgeTileView>
     */
    private function latest(array $families, BadgeTileView $legend): array
    {
        $earned = array_filter(
            [$legend, ...array_merge(...array_map(static fn (BadgeFamilyView $family): array => $family->tiles, $families))],
            static fn (BadgeTileView $tile): bool => $tile->isEarned(),
        );
        usort($earned, static fn (BadgeTileView $a, BadgeTileView $b): int => [$b->unlockedAt, $b->tier->value] <=> [$a->unlockedAt, $a->tier->value]);

        return \array_slice($earned, 0, self::LATEST_LIMIT);
    }

    /**
     * @param list<BadgeFamilyView> $families
     * @return list<BadgeTileView>
     */
    private function next(array $families): array
    {
        $next = [];

        foreach ($families as $family) {
            foreach ($family->tiles as $tile) {
                if (! $tile->isEarned()) {
                    $next[] = $tile;
                    break;
                }
            }
        }
        usort($next, static fn (BadgeTileView $a, BadgeTileView $b): int => $b->progressPercent <=> $a->progressPercent);

        return \array_slice($next, 0, self::NEXT_LIMIT);
    }

    /**
     * @param list<BadgeFamilyView> $families
     * @return array<string, bool>
     */
    private function rubyByFamily(array $families): array
    {
        $rubies = [];

        foreach ($families as $family) {
            $rubies[$family->family->value] = $family->isComplete;
        }

        return $rubies;
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
