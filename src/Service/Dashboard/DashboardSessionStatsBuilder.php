<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Repository\WorkoutStatsRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Stats du widget Séance : 3 filtres (dernière journée, semaine, mois), toujours débloqués dès une
 * séance, plus le total annuel — fixe, indépendant du filtre actif, même bornage 1er janvier →
 * aujourd'hui que le total annuel du widget Tonnage. PR de poids et records de reps suivent la
 * même définition que `WorkoutShowController`, détectés sur tout l'historique.
 *
 * @phpstan-type FilterStats array{exercises: int, sets: int, reps: int, sessions: int, sessionsLabel: string, exercisesLabel: string, setsLabel: string, prCount: int, prLabel: string, repsRecordCount: int, repsRecordLabel: string}
 * @phpstan-type SessionStats array{year: int, annualTotal: int, last: FilterStats, week: FilterStats, month: FilterStats}
 */
final readonly class DashboardSessionStatsBuilder
{
    public function __construct(
        private WorkoutStatsRepository $workoutStatsRepository,
        private DashboardPrService $prService,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param int $daySessionsCount séances de la dernière journée d'entraînement — plusieurs peuvent
     *                              partager cette date, d'où un compte fourni plutôt que recalculé
     * @return SessionStats
     */
    public function build(User $user, DashboardPeriods $periods, int $daySessionsCount): array
    {
        $prCounts = $this->prService->countPrsByFilter($user, $periods->day, $periods->week, $periods->month);
        $repsRecordCounts = $this->prService->countRepsRecordsByFilter($user, $periods->day, $periods->week, $periods->month);

        return [
            'year' => (int) $periods->year->start->format('Y'),
            'annualTotal' => $this->countSessions($user, $periods->year),
            'last' => $this->buildFilter($user, $periods->day, [
                'sessions' => $daySessionsCount,
                'pr' => $prCounts['last'],
                'repsRecord' => $repsRecordCounts['last'],
            ]),
            'week' => $this->buildFilter($user, $periods->week, [
                'sessions' => $this->countSessions($user, $periods->week),
                'pr' => $prCounts['week'],
                'repsRecord' => $repsRecordCounts['week'],
            ]),
            'month' => $this->buildFilter($user, $periods->month, [
                'sessions' => $this->countSessions($user, $periods->month),
                'pr' => $prCounts['month'],
                'repsRecord' => $repsRecordCounts['month'],
            ]),
        ];
    }

    /**
     * @param array{sessions: int, pr: int, repsRecord: int} $counts
     * @return FilterStats
     */
    private function buildFilter(User $user, DashboardPeriod $period, array $counts): array
    {
        $totals = $this->workoutStatsRepository->findExerciseSetRepTotals($user, $period->start, $period->end);

        return [
            ...$totals,
            'sessions' => $counts['sessions'],
            'sessionsLabel' => $this->buildSessionsLabel($counts['sessions']),
            'exercisesLabel' => $this->buildExercisesLabel($totals['exercises']),
            'setsLabel' => $this->buildSetsLabel($totals['sets']),
            'prCount' => $counts['pr'],
            'prLabel' => $this->buildPrLabel($counts['pr']),
            'repsRecordCount' => $counts['repsRecord'],
            'repsRecordLabel' => $this->buildRepsRecordLabel($counts['repsRecord']),
        ];
    }

    private function countSessions(User $user, DashboardPeriod $period): int
    {
        return $this->workoutStatsRepository->countByUserAndDate($user, $period->start, $period->end);
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
