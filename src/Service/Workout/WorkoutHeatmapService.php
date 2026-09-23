<?php

declare(strict_types=1);

namespace App\Service\Workout;

use App\Entity\User;
use App\Repository\WorkoutRepository;
use App\Service\Dashboard\DashboardPeriodCalculator;

/**
 * Calendrier heatmap façon GitHub pour l'onglet "Calendrier" de la liste des séances — intensité
 * par jour = durée cumulée des séances de ce jour (pas le nombre de séances, cf. décision UX dans
 * la mémoire du projet), semaines couvertes par un deload marquées à part. Grille de `$weekCount`
 * semaines pleines (lundi → dimanche), se terminant sur la semaine en cours — un an sur l'onglet
 * Calendrier, moins sur le widget dashboard qui n'a qu'une demi-colonne.
 *
 * @phpstan-type HeatmapData array{
 *     weeks: list<array{
 *         days: list<array{date: \DateTimeImmutable, level: int, isDeload: bool}>,
 *         showsMonthLabel: bool,
 *     }>,
 * }
 */
final readonly class WorkoutHeatmapService
{
    public const int FULL_YEAR_WEEKS = 53;

    private const int DAYS_PER_WEEK = 7;

    // Un libellé de mois ("sept.") occupe ~3 colonnes : en dessous, il chevauche le suivant.
    private const int MIN_WEEKS_PER_MONTH_LABEL = 3;

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
     * @return HeatmapData
     */
    public function build(User $user, int $weekCount = self::FULL_YEAR_WEEKS): array
    {
        $currentWeekStart = $this->periodCalculator->weekStartOf(new \DateTimeImmutable());
        $gridStart = $currentWeekStart->modify(\sprintf('-%d days', ($weekCount - 1) * self::DAYS_PER_WEEK));

        $durationByDay = $this->buildDurationByDayMap($user, $gridStart);
        $deloadDaySet = $this->deloadPeriodSetService->dayKeySet($user);

        $weeks = [];

        for ($w = 0; $weekCount > $w; $w++) {
            $weekStart = $gridStart->modify(\sprintf('+%d days', $w * self::DAYS_PER_WEEK));
            $weeks[] = [
                'days' => $this->buildWeekDays($weekStart, $durationByDay, $deloadDaySet),
                'showsMonthLabel' => $this->startsLabelledMonth($weekStart, 0 === $w),
            ];
        }

        return [
            'weeks' => $weeks,
        ];
    }

    /**
     * @param array<string, int>  $durationByDay
     * @param array<string, true> $deloadDaySet
     * @return list<array{date: \DateTimeImmutable, level: int, isDeload: bool}>
     */
    private function buildWeekDays(\DateTimeImmutable $weekStart, array $durationByDay, array $deloadDaySet): array
    {
        $days = [];

        for ($d = 0; self::DAYS_PER_WEEK > $d; $d++) {
            $day = $weekStart->modify(\sprintf('+%d days', $d));
            $dayKey = $day->format('Y-m-d');
            $days[] = [
                'date' => $day,
                'level' => $this->levelFor($durationByDay[$dayKey] ?? null),
                'isDeload' => isset($deloadDaySet[$dayKey]),
            ];
        }

        return $days;
    }

    /**
     * Une colonne porte le libellé du mois quand elle en est la première. Exception : le mois
     * entamé en tout début de grille n'a souvent qu'une ou deux colonnes, son libellé chevaucherait
     * celui du mois suivant — il n'est affiché que s'il a la place.
     */
    private function startsLabelledMonth(\DateTimeImmutable $weekStart, bool $isFirstWeekOfGrid): bool
    {
        if (! $isFirstWeekOfGrid) {
            return $weekStart->format('Y-m') !== $weekStart->modify('-1 week')->format('Y-m');
        }

        $labelEnd = $weekStart->modify(\sprintf('+%d weeks', self::MIN_WEEKS_PER_MONTH_LABEL - 1));

        return $weekStart->format('Y-m') === $labelEnd->format('Y-m');
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
