<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\WorkoutMuscleRepository;
use App\Repository\WorkoutRepository;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardMuscleDistributionService;
use App\Service\Dashboard\DashboardPeriodCalculator;
use App\Service\Dashboard\DashboardPrService;
use App\Service\Dashboard\DashboardRegularityService;
use App\Service\Dashboard\DashboardTonnageService;
use App\Service\Dashboard\DashboardUnlockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardUnlockService $dashboardUnlockService,
        private readonly DashboardRegularityService $regularityService,
        private readonly DashboardMuscleDistributionService $muscleDistributionService,
        private readonly DashboardTonnageService $tonnageService,
        private readonly DashboardPrService $prService,
        private readonly DashboardPeriodCalculator $periodCalculator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        path: [
            'en' => '/dashboard',
            'fr' => '/tableau-de-bord',
            'it' => '/cruscotto',
            'es' => '/panel',
            'pt' => '/painel',
            'de' => '/uebersicht',
            'nl' => '/overzicht',
            'pl' => '/panel',
        ],
        name: 'app_dashboard'
    )]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        WorkoutRepository $workoutRepository,
        WorkoutMuscleRepository $workoutMuscleRepository,
        WorkoutStatsRepository $workoutStatsRepository,
    ): Response {
        $user = $this->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        $dashboardState = $this->dashboardUnlockService->getStateForUser($user);

        if (0 === $dashboardState->workoutCount) {
            return $this->render('dashboard/dashboard-empty-responsive.html.twig', [
                'user' => $user,
                'dashboardState' => $dashboardState,
            ]);
        }

        $lastPerformedAt = $workoutRepository->findLastPerformedAtByUser($user);

        if (null === $lastPerformedAt) {
            throw new \LogicException('Expected a workout for user with non-zero workout count.');
        }

        // Bornes de la semaine, du mois courant et de la dernière journée d'entraînement,
        // réutilisées pour le widget Muscles et le widget Séance
        $now = new \DateTimeImmutable();
        $day = $this->periodCalculator->dayOf($lastPerformedAt);
        $week = $this->periodCalculator->currentWeek($now);
        $month = $this->periodCalculator->currentMonthElapsed($now);

        // Résumé du widget Séance pour la dernière journée (totaux sets/reps + SVG IDs), même
        // mécanique que les filtres Semaine/Mois — plusieurs séances peuvent partager cette date.
        $dayIds = $workoutStatsRepository->findIdsByUserAndDateRange($user, $day->start, $day->end);
        $daySvgIds = $workoutMuscleRepository->findSvgIdsByWorkoutIds($dayIds);
        $sessionPrimary = $daySvgIds['primary'];
        $sessionSecondary = $daySvgIds['secondary'];

        // SVG IDs + répartition par groupe musculaire pour les filtres Semaine/Mois du widget Muscles (uniquement si débloqué)
        $weekSvgIds = [
            'primary' => [],
            'secondary' => [],
        ];
        $monthSvgIds = [
            'primary' => [],
            'secondary' => [],
        ];
        $weekBars = [
            'bars' => [],
            'remainingCount' => 0,
        ];
        $monthBars = [
            'bars' => [],
            'remainingCount' => 0,
        ];

        if ($dashboardState->muscleWeekMonthUnlocked) {
            // Sur tout l'historique, pas seulement la période du filtre — sert à afficher
            // "depuis quand ce muscle n'a pas été sollicité", une seule requête pour les 2 filtres.
            $lastSolicitationDates = $workoutMuscleRepository->findLastSolicitationDatesByMuscleGroup($user);

            $weekIds = $workoutStatsRepository->findIdsByUserAndDateRange($user, $week->start, $week->end);
            $weekSvgIds = $workoutMuscleRepository->findSvgIdsByWorkoutIds($weekIds);
            $weekBars = $this->muscleDistributionService->getBars($weekIds, $lastSolicitationDates);

            $monthIds = $workoutStatsRepository->findIdsByUserAndDateRange($user, $month->start, $month->end);
            $monthSvgIds = $workoutMuscleRepository->findSvgIdsByWorkoutIds($monthIds);
            $monthBars = $this->muscleDistributionService->getBars($monthIds, $lastSolicitationDates);
        }

        // Répartition par groupe musculaire pour le filtre Séance (dernière journée)
        $sessionBars = $this->muscleDistributionService->getBars($dayIds);

        // PR de poids et records de reps par filtre (Dernière journée/Semaine/Mois courant), même
        // définition que WorkoutShowController, détectés sur tout l'historique.
        $prCounts = $this->prService->countPrsByFilter(
            $user,
            $day,
            $week,
            $month,
        );
        $repsRecordCounts = $this->prService->countRepsRecordsByFilter(
            $user,
            $day,
            $week,
            $month,
        );

        // Total annuel de séances — fixe, indépendant du filtre actif, même bornage 1er janvier ->
        // 31 décembre que le total annuel du widget Tonnage.
        $year = $this->periodCalculator->currentYearElapsed($now);
        $annualSessionsTotal = $workoutStatsRepository->countByUserAndDate($user, $year->start, $year->end);

        // Stats du widget Séance (3 filtres, toujours débloqués dès 1 séance) — "last" traité
        // comme les autres filtres, sur la période de la dernière journée d'entraînement.
        $daySessionsCount = \count($dayIds);
        $weekSessionsCount = $workoutStatsRepository->countByUserAndDate($user, $week->start, $week->end);
        $monthSessionsCount = $workoutStatsRepository->countByUserAndDate($user, $month->start, $month->end);
        $dayTotals = $workoutStatsRepository->findExerciseSetRepTotals($user, $day->start, $day->end);
        $weekTotals = $workoutStatsRepository->findExerciseSetRepTotals($user, $week->start, $week->end);
        $monthTotals = $workoutStatsRepository->findExerciseSetRepTotals($user, $month->start, $month->end);

        $sessionStats = [
            'year' => (int) $now->format('Y'),
            'annualTotal' => $annualSessionsTotal,
            'last' => [
                ...$dayTotals,
                'sessions' => $daySessionsCount,
                'sessionsLabel' => $this->buildSessionsLabel($daySessionsCount),
                'exercisesLabel' => $this->buildExercisesLabel($dayTotals['exercises']),
                'setsLabel' => $this->buildSetsLabel($dayTotals['sets']),
                'prCount' => $prCounts['last'],
                'prLabel' => $this->buildPrLabel($prCounts['last']),
                'repsRecordCount' => $repsRecordCounts['last'],
                'repsRecordLabel' => $this->buildRepsRecordLabel($repsRecordCounts['last']),
            ],
            'week' => [
                ...$weekTotals,
                'sessions' => $weekSessionsCount,
                'sessionsLabel' => $this->buildSessionsLabel($weekSessionsCount),
                'exercisesLabel' => $this->buildExercisesLabel($weekTotals['exercises']),
                'setsLabel' => $this->buildSetsLabel($weekTotals['sets']),
                'prCount' => $prCounts['week'],
                'prLabel' => $this->buildPrLabel($prCounts['week']),
                'repsRecordCount' => $repsRecordCounts['week'],
                'repsRecordLabel' => $this->buildRepsRecordLabel($repsRecordCounts['week']),
            ],
            'month' => [
                ...$monthTotals,
                'sessions' => $monthSessionsCount,
                'sessionsLabel' => $this->buildSessionsLabel($monthSessionsCount),
                'exercisesLabel' => $this->buildExercisesLabel($monthTotals['exercises']),
                'setsLabel' => $this->buildSetsLabel($monthTotals['sets']),
                'prCount' => $prCounts['month'],
                'prLabel' => $this->buildPrLabel($prCounts['month']),
                'repsRecordCount' => $repsRecordCounts['month'],
                'repsRecordLabel' => $this->buildRepsRecordLabel($repsRecordCounts['month']),
            ],
        ];

        // Données de régularité (uniquement si débloqué)
        $regularityData = null;
        if ($dashboardState->regularityUnlocked) {
            $regularityData = $this->regularityService->getData($user);
        }

        // Tonnage (chiffre toujours débloqué dès 1 séance, courbe gérée par filtre dans le service)
        $tonnageData = $this->tonnageService->getData($user);

        return $this->render('dashboard/dashboard.html.twig', [
            'user' => $user,
            'dashboardState' => $dashboardState,
            'sessionStats' => $sessionStats,
            'tonnageData' => $tonnageData,
            'sessionPrimary' => $sessionPrimary,
            'sessionSecondary' => $sessionSecondary,
            'weekPrimary' => $weekSvgIds['primary'],
            'weekSecondary' => $weekSvgIds['secondary'],
            'monthPrimary' => $monthSvgIds['primary'],
            'monthSecondary' => $monthSvgIds['secondary'],
            'sessionBars' => $sessionBars,
            'weekBars' => $weekBars,
            'monthBars' => $monthBars,
            'regularityData' => $regularityData,
        ]);
    }

    private function buildPrLabel(int $count): string
    {
        return 0 === $count
            ? $this->translator->trans('dashboard.widget.session.pr_count_zero', [], 'navigation')
            : $this->translator->trans('dashboard.widget.session.pr_count', [
                'count' => $count,
            ], 'navigation');
    }

    private function buildRepsRecordLabel(int $count): string
    {
        return $this->translator->trans('dashboard.widget.session.reps_record_count', [
            'count' => $count,
        ], 'navigation');
    }

    private function buildSessionsLabel(int $count): string
    {
        return $this->translator->trans('dashboard.widget.session.sessions', [
            'count' => $count,
        ], 'navigation');
    }

    private function buildExercisesLabel(int $count): string
    {
        return $this->translator->trans('dashboard.widget.session.exercises', [
            'count' => $count,
        ], 'navigation');
    }

    private function buildSetsLabel(int $count): string
    {
        return $this->translator->trans('workout.show.metrics.sets', [
            'count' => $count,
        ], 'navigation');
    }
}
