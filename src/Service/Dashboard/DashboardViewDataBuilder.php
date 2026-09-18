<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Repository\WorkoutRepository;
use App\Repository\WorkoutStatsRepository;
use App\Service\Goal\GoalCardFormatter;
use App\Service\Goal\GoalProgress;

/**
 * Calcule le dashboard d'un utilisateur donné (`$subject`), indépendamment de qui le consulte :
 * réutilisable pour afficher le dashboard d'un autre utilisateur. À n'appeler que si
 * `$dashboardState->workoutCount` est non nul — un dashboard vide a sa propre vue.
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

    public function build(User $subject, DashboardState $dashboardState): DashboardViewData
    {
        $periods = $this->resolvePeriods($subject);
        $dayIds = $this->workoutStatsRepository->findIdsByUserAndDateRange($subject, $periods->day->start, $periods->day->end);
        $goalState = $this->goalService->getStateForUser($subject);
        $visibleWidgets = $this->resolveVisibleWidgets($subject, $dashboardState);

        return new DashboardViewData(
            $dashboardState,
            $this->sessionStatsBuilder->build($subject, $periods, \count($dayIds)),
            $this->muscleViewBuilder->build($subject, $periods, $dayIds, $dashboardState->muscleWeekMonthUnlocked),
            $this->tonnageService->getData($subject),
            $dashboardState->regularityUnlocked ? $this->regularityService->getData($subject) : null,
            $this->formatGoalCards($goalState->current, $subject),
            $this->formatGoalCards($goalState->achieved, $subject),
            $visibleWidgets,
            $this->hasNoVisibleContent($visibleWidgets, $dashboardState),
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
     * Le placeholder "verrouillé" de Régularité occupe toujours de l'espace quand elle n'est pas
     * encore débloquée — l'écran n'est réellement vide que si aucun widget débloqué n'est affiché
     * ET que Régularité est débloquée (donc masquable, donc potentiellement masquée).
     *
     * @param array<string, bool> $visibleWidgets
     */
    private function hasNoVisibleContent(array $visibleWidgets, DashboardState $dashboardState): bool
    {
        return ! \in_array(true, $visibleWidgets, true) && $dashboardState->regularityUnlocked;
    }

    /**
     * @param array<GoalProgress> $progressList
     * @return array<array<string, mixed>>
     */
    private function formatGoalCards(array $progressList, User $subject): array
    {
        return array_map(
            fn (GoalProgress $progress): array => $this->goalCardFormatter->format($progress, $subject),
            $progressList,
        );
    }
}
