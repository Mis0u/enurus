<?php

declare(strict_types=1);

namespace App\Service\Badge\View;

/**
 * Tout ce qu'affichent le widget Badges et la section "Tous mes badges", déjà calculé.
 */
final readonly class BadgeCollectionView
{
    /**
     * @param list<BadgeFamilyView>          $families
     * @param list<BadgeTileView>            $latest          derniers badges obtenus, du plus récent au plus ancien
     * @param list<BadgeTileView>            $next            prochains paliers, du plus avancé au moins avancé
     * @param array<string, bool>            $rubyByFamily    clé = BadgeFamilyEnum::value, pour l'encart Légende
     */
    public function __construct(
        public array $families,
        public BadgeTileView $legend,
        public array $latest,
        public array $next,
        public array $rubyByFamily,
        public int $earnedCount,
        public int $totalCount,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->earnedCount === $this->totalCount;
    }
}
