<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Repository\WorkoutMuscleRepository;
use App\Repository\WorkoutStatsRepository;

/**
 * Semaine et mois ne sont calculés qu'une fois débloqués : verrouillés, ils restent vides sans
 * aucune requête.
 */
final readonly class DashboardMuscleViewBuilder
{
    public function __construct(
        private WorkoutStatsRepository $workoutStatsRepository,
        private WorkoutMuscleRepository $workoutMuscleRepository,
        private DashboardMuscleDistributionService $muscleDistributionService,
    ) {
    }

    /**
     * @param string[] $dayIds workouts de la dernière journée d'entraînement
     */
    public function build(User $user, DashboardPeriods $periods, array $dayIds, bool $weekAndMonthUnlocked): DashboardMuscleView
    {
        $session = $this->buildFilter($dayIds);

        if (! $weekAndMonthUnlocked) {
            return new DashboardMuscleView($session, DashboardMuscleFilterView::empty(), DashboardMuscleFilterView::empty());
        }

        // Sur tout l'historique, pas seulement la période du filtre — sert à afficher "depuis quand
        // ce muscle n'a pas été sollicité", une seule requête pour les 2 filtres.
        $lastSolicitationDates = $this->workoutMuscleRepository->findLastSolicitationDatesByMuscleGroup($user);

        return new DashboardMuscleView(
            $session,
            $this->buildFilterForPeriod($user, $periods->week, $lastSolicitationDates),
            $this->buildFilterForPeriod($user, $periods->month, $lastSolicitationDates),
        );
    }

    /**
     * @param array<string, \DateTimeImmutable> $lastSolicitationDates
     */
    private function buildFilterForPeriod(User $user, DashboardPeriod $period, array $lastSolicitationDates): DashboardMuscleFilterView
    {
        $workoutIds = $this->workoutStatsRepository->findIdsByUserAndDateRange($user, $period->start, $period->end);

        return $this->buildFilter($workoutIds, $lastSolicitationDates);
    }

    /**
     * @param string[]                          $workoutIds
     * @param array<string, \DateTimeImmutable> $lastSolicitationDates
     */
    private function buildFilter(array $workoutIds, array $lastSolicitationDates = []): DashboardMuscleFilterView
    {
        $svgIds = $this->workoutMuscleRepository->findSvgIdsByWorkoutIds($workoutIds);

        return new DashboardMuscleFilterView(
            $svgIds[MuscleTypeEnum::PRIMARY->value],
            $svgIds[MuscleTypeEnum::SECONDARY->value],
            $this->muscleDistributionService->getBars($workoutIds, $lastSolicitationDates),
        );
    }
}
