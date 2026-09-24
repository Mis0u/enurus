<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Service\Badge\View\BadgeCollectionView;
use App\Service\ProfileSharing\ProfileConnectionEntry;
use App\Service\Workout\WorkoutHeatmapService;

/**
 * Tout ce que la vue dashboard affiche, déjà calculé : le controller ne fait que le transmettre.
 *
 * @phpstan-import-type SessionStats from DashboardSessionStatsBuilder
 * @phpstan-import-type HeatmapData from WorkoutHeatmapService
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
     * @param HeatmapData|null                $heatmapData       nul tant que le widget Calendrier n'est pas visible
     * @param list<ProfileConnectionEntry>    $connections       connexions acceptées de celui qui regarde, vide tant que
     *                                                           le widget Connexions (personnel) n'est pas visible
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
        public ?array $heatmapData,
        public array $connections,
    ) {
    }
}
