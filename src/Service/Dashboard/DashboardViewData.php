<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Service\Badge\View\BadgeCollectionView;

/**
 * Tout ce que la vue dashboard affiche, déjà calculé : le controller ne fait que le transmettre.
 *
 * @phpstan-import-type SessionStats from DashboardSessionStatsBuilder
 */
final readonly class DashboardViewData
{
    /**
     * @param SessionStats                    $sessionStats
     * @param array<string, mixed>            $tonnageData
     * @param array<string, mixed>|null       $regularityData    nul tant que le widget Régularité est verrouillé
     * @param array<array<string, mixed>>     $goalCurrentCards
     * @param array<array<string, mixed>>     $goalAchievedCards
     * @param array<string, bool>             $visibleWidgets    clé = DashboardWidgetEnum::value
     * @param bool                            $hasNoVisibleWidgets  aucun widget n'est visible (masqués ou verrouillés)
     * @param bool                            $hasNoVisibleContent  l'écran serait réellement vide : aucun widget visible ET
     *                                                              Régularité débloquée — verrouillée, son placeholder
     *                                                              occupe toujours de l'espace
     * @param BadgeCollectionView             $badges            widget Badges et section "Tous mes badges"
     */
    public function __construct(
        public DashboardState $dashboardState,
        public array $sessionStats,
        public DashboardMuscleView $muscles,
        public array $tonnageData,
        public ?array $regularityData,
        public array $goalCurrentCards,
        public array $goalAchievedCards,
        public array $visibleWidgets,
        public bool $hasNoVisibleWidgets,
        public bool $hasNoVisibleContent,
        public BadgeCollectionView $badges,
    ) {
    }
}
