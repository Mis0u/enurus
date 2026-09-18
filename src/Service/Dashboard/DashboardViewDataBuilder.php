<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Repository\WorkoutRepository;
use App\Repository\WorkoutStatsRepository;
use App\Service\Goal\GoalCardFormatter;
use App\Service\Goal\GoalProgress;

/**
 * Calcule le dashboard d'un utilisateur (`$subject`) tel que le lit `$viewer` : les données et les
 * widgets visibles sont ceux du propriétaire, mais l'unité de poids et la langue des libellés
 * dépendent de celui qui regarde. Pour son propre dashboard, les deux sont le même utilisateur.
 * À n'appeler que si `$dashboardState->workoutCount` est non nul — un dashboard vide a sa propre vue.
 */
final readonly class DashboardViewDataBuilder
{
    public function __construct(
        private WorkoutRepository $workoutRepository,
        private WorkoutStatsRepository $workoutStatsRepository,
        private DashboardPeriodCalculator $periodCalculator,
        private DashboardSessionStatsBuilder $sessionStatsBuilder,
        private DashboardMuscleViewBuilder $muscleViewBuilder,
        private DashboardRegularityService $regularityService,
        private DashboardTonnageService $tonnageService,
        private DashboardGoalService $goalService,
        private GoalCardFormatter $goalCardFormatter,
        private DashboardWidgetUnlockResolver $widgetUnlockResolver,
    ) {
    }

    public function build(User $subject, User $viewer, DashboardState $dashboardState): DashboardViewData
    {
        $periods = $this->resolvePeriods($subject);
        $dayIds = $this->workoutStatsRepository->findIdsByUserAndDateRange($subject, $periods->day->start, $periods->day->end);
        $goalState = $this->goalService->getStateForUser($subject);
        $visibleWidgets = $this->resolveVisibleWidgets($subject, $dashboardState);
        $hasNoVisibleWidgets = ! \in_array(true, $visibleWidgets, true);

        return new DashboardViewData(
            $dashboardState,
            $this->sessionStatsBuilder->build($subject, $periods, \count($dayIds)),
            $this->muscleViewBuilder->build($subject, $periods, $dayIds, $dashboardState->muscleWeekMonthUnlocked),
            $this->tonnageService->getData($subject, $viewer),
            $dashboardState->regularityUnlocked ? $this->regularityService->getData($subject) : null,
            $this->formatGoalCards($goalState->current, $viewer),
            $this->formatGoalCards($goalState->achieved, $viewer),
            $visibleWidgets,
            $hasNoVisibleWidgets,
            $hasNoVisibleWidgets && $dashboardState->regularityUnlocked,
        );
    }

    /**
     * Bornes de la semaine, du mois courant et de la dernière journée d'entraînement, réutilisées
     * pour le widget Muscles et le widget Séance.
     */
    private function resolvePeriods(User $subject): DashboardPeriods
    {
        $lastPerformedAt = $this->workoutRepository->findLastPerformedAtByUser($subject)
            ?? throw new \LogicException('Expected a workout for user with non-zero workout count.');

        $now = new \DateTimeImmutable();

        return new DashboardPeriods(
            $this->periodCalculator->dayOf($lastPerformedAt),
            $this->periodCalculator->currentWeek($now),
            $this->periodCalculator->currentMonthElapsed($now),
            $this->periodCalculator->currentYearElapsed($now),
        );
    }

    /**
     * Un widget s'affiche s'il est débloqué ET que l'utilisateur ne l'a pas masqué en réglages —
     * même source de vérité que la liste de cases à cocher proposée dans les réglages
     * (`DashboardWidgetUnlockResolver`), pour ne jamais désynchroniser les deux.
     *
     * @return array<string, bool> clé = DashboardWidgetEnum::value
     */
    private function resolveVisibleWidgets(User $subject, DashboardState $dashboardState): array
    {
        $visibleWidgets = [];

        foreach ($this->widgetUnlockResolver->resolve($subject, $dashboardState) as $widget => $unlocked) {
            $visibleWidgets[$widget] = $unlocked && ! \in_array($widget, $subject->hiddenWidgets, true);
        }

        return $visibleWidgets;
    }

    /**
     * @param array<GoalProgress> $progressList
     * @return array<array<string, mixed>>
     */
    private function formatGoalCards(array $progressList, User $viewer): array
    {
        return array_map(
            fn (GoalProgress $progress): array => $this->goalCardFormatter->format($progress, $viewer),
            $progressList,
        );
    }
}
