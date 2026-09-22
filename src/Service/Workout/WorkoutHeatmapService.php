<?php

declare(strict_types=1);

namespace App\Service\Workout;

use App\Entity\User;
use App\Repository\WorkoutRepository;
use App\Service\Dashboard\DashboardPeriodCalculator;

/**
 * Calendrier heatmap façon GitHub pour l'onglet "Calendrier" de la liste des séances — intensité
 * par jour = durée cumulée des séances de ce jour (pas le nombre de séances, cf. décision UX dans
 * la mémoire du projet), semaines couvertes par un deload marquées à part. Grille de
 * `WEEKS_IN_GRID` semaines pleines (lundi → dimanche), se terminant sur la semaine en cours.
 */
final readonly class WorkoutHeatmapService
{
    private const int WEEKS_IN_GRID = 53;

    private const int DAYS_PER_WEEK = 7;

    private const int SHORT_SESSION_MAX_MINUTES = 45;

    private const int MEDIUM_SESSION_MAX_MINUTES = 90;

    private const int LEVEL_NONE = 0;

    private const int LEVEL_SHORT = 1;

    private const int LEVEL_MEDIUM = 2;

    private const int LEVEL_HIGH = 3;

    public function __construct(
        private WorkoutRepository $workoutRepository,
        private DashboardPeriodCalculator $periodCalculator,
        private DeloadPeriodSetService $deloadPeriodSetService,
    ) {
    }

    /**
     * @return array{
     *     weeks: list<array{
     *         days: list<array{date: \DateTimeImmutable, level: int, isDeload: bool}>,
     *         isNewMonth: bool,
     *     }>,
     * }
     */
    public function build(User $user): array
    {
        $currentWeekStart = $this->periodCalculator->weekStartOf(new \DateTimeImmutable());
        $gridStart = $currentWeekStart->modify(\sprintf('-%d days', (self::WEEKS_IN_GRID - 1) * self::DAYS_PER_WEEK));

        $durationByDay = $this->buildDurationByDayMap($user, $gridStart);
        $deloadDaySet = $this->deloadPeriodSetService->dayKeySet($user);

        $weeks = [];
        $day = $gridStart;
        $previousMonth = null;

        for ($w = 0; self::WEEKS_IN_GRID > $w; $w++) {
            $days = [];

            for ($d = 0; self::DAYS_PER_WEEK > $d; $d++) {
                $dayKey = $day->format('Y-m-d');
                $days[] = [
                    'date' => $day,
                    'level' => $this->levelFor($durationByDay[$dayKey] ?? null),
                    'isDeload' => isset($deloadDaySet[$dayKey]),
                ];
                $day = $day->modify('+1 day');
            }

            $month = $days[0]['date']->format('Y-m');
            $weeks[] = [
                'days' => $days,
                'isNewMonth' => $month !== $previousMonth,
            ];
            $previousMonth = $month;
        }

        return [
            'weeks' => $weeks,
        ];
    }

    /**
     * @return array<string, int> clé `Y-m-d` => durée cumulée en minutes ce jour-là
     */
    private function buildDurationByDayMap(User $user, \DateTimeImmutable $since): array
    {
        $durationByDay = [];

        foreach ($this->workoutRepository->findPerformedAtAndDurationSince($user, $since) as $row) {
            $dayKey = $row['performedAt']->format('Y-m-d');
            $durationByDay[$dayKey] = ($durationByDay[$dayKey] ?? 0) + ($row['duration'] ?? 0);
        }

        return $durationByDay;
    }

    private function levelFor(?int $totalMinutes): int
    {
        if (null === $totalMinutes || 0 === $totalMinutes) {
            return self::LEVEL_NONE;
        }

        if (self::SHORT_SESSION_MAX_MINUTES > $totalMinutes) {
            return self::LEVEL_SHORT;
        }

        if (self::MEDIUM_SESSION_MAX_MINUTES > $totalMinutes) {
            return self::LEVEL_MEDIUM;
        }

        return self::LEVEL_HIGH;
    }
}
