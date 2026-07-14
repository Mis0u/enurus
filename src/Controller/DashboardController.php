<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\WorkoutRepository;
use App\Service\Dashboard\DashboardMuscleDistributionService;
use App\Service\Dashboard\DashboardPeriodCalculator;
use App\Service\Dashboard\DashboardPrService;
use App\Service\Dashboard\DashboardRegularityService;
use App\Service\Dashboard\DashboardSessionSummaryService;
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
        private readonly DashboardSessionSummaryService $sessionSummaryService,
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

        $lastWorkout = $workoutRepository->findLatestByUser($user);

        if (null === $lastWorkout) {
            throw new \LogicException('Expected a workout for user with non-zero workout count.');
        }

        // Résumé du widget Séance (totaux sets/reps + SVG IDs) depuis le workout déjà chargé
        $sessionSummary = $this->sessionSummaryService->summarize($lastWorkout);
        $sessionPrimary = $sessionSummary['primarySvgIds'];
        $sessionSecondary = $sessionSummary['secondarySvgIds'];

        // Bornes de la semaine et du mois courant, réutilisées pour le widget Muscles et le widget Séance
        $now = new \DateTimeImmutable();
        $week = $this->periodCalculator->currentWeek($now);
        $month = $this->periodCalculator->currentMonthElapsed($now);

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
            $lastSolicitationDates = $workoutRepository->findLastSolicitationDatesByMuscleGroup($user);

            $weekIds = $workoutRepository->findIdsByUserAndDateRange($user, $week->start, $week->end);
            $weekSvgIds = $workoutRepository->findSvgIdsByWorkoutIds($weekIds);
            $weekBars = $this->muscleDistributionService->getBars($weekIds, $lastSolicitationDates);

            $monthIds = $workoutRepository->findIdsByUserAndDateRange($user, $month->start, $month->end);
            $monthSvgIds = $workoutRepository->findSvgIdsByWorkoutIds($monthIds);
            $monthBars = $this->muscleDistributionService->getBars($monthIds, $lastSolicitationDates);
        }

        // Répartition par groupe musculaire pour le filtre Séance (depuis le workout déjà chargé)
        $sessionBars = $this->muscleDistributionService->getBars([(string) $lastWorkout->id]);

        // PR de poids et records de reps par filtre (Dernière séance/Semaine/Mois courant), même
        // définition que WorkoutShowController, détectés sur tout l'historique.
        $prCounts = $this->prService->countPrsByFilter(
            $user,
            (string) $lastWorkout->id,
            $week,
            $month,
        );
        $repsRecordCounts = $this->prService->countRepsRecordsByFilter(
            $user,
            (string) $lastWorkout->id,
            $week,
            $month,
        );

        // Stats du widget Séance (3 filtres, toujours débloqués dès 1 séance)
        $sessionStats = [
            'last' => [
                'exercises' => $lastWorkout->workoutExercises->count(),
                'sets' => $sessionSummary['totalSets'],
                'reps' => $sessionSummary['totalReps'],
                'prCount' => $prCounts['last'],
                'prLabel' => $this->buildPrLabel($prCounts['last']),
                'repsRecordCount' => $repsRecordCounts['last'],
                'repsRecordLabel' => $this->buildRepsRecordLabel($repsRecordCounts['last']),
            ],
            'week' => [
                ...$workoutRepository->findExerciseSetRepTotals($user, $week->start, $week->end),
                'prCount' => $prCounts['week'],
                'prLabel' => $this->buildPrLabel($prCounts['week']),
                'repsRecordCount' => $repsRecordCounts['week'],
                'repsRecordLabel' => $this->buildRepsRecordLabel($repsRecordCounts['week']),
            ],
            'month' => [
                ...$workoutRepository->findExerciseSetRepTotals($user, $month->start, $month->end),
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
}
