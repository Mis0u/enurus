<?php

declare(strict_types=1);

namespace App\Service\Workout;

use App\Entity\User;
use App\Repository\WorkoutTonnageRepository;
use App\Service\Dashboard\DashboardPeriodCalculator;

/**
 * Calendrier heatmap façon GitHub pour l'onglet "Calendrier" de la liste des séances — intensité
 * par jour = tonnage cumulé des séances de ce jour, comparé aux autres jours de l'année
 * (`HeatmapLevelCalculator`), semaines couvertes par un deload marquées à part. Pas la durée :
 * beaucoup d'utilisateurs ne la renseignent jamais, leurs séances restaient invisibles. Grille de `$weekCount`
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

    private const int LEVEL_REST = 0;

    public function __construct(
        private WorkoutTonnageRepository $tonnageRepository,
        private DashboardPeriodCalculator $periodCalculator,
        private DeloadPeriodSetService $deloadPeriodSetService,
        private HeatmapLevelCalculator $levelCalculator,
    ) {
    }

    /**
     * @return HeatmapData
     */
    public function build(User $user, int $weekCount = self::FULL_YEAR_WEEKS): array
    {
        $currentWeekStart = $this->periodCalculator->weekStartOf(new \DateTimeImmutable());
        $gridStart = $this->gridStartOf($currentWeekStart, $weekCount);

        $levelByDay = $this->levelCalculator->levelByDay($this->buildYearTonnageByDayMap($user, $currentWeekStart));
        $deloadDaySet = $this->deloadPeriodSetService->dayKeySet($user);

        $weeks = [];

        for ($w = 0; $weekCount > $w; $w++) {
            $weekStart = $gridStart->modify(\sprintf('+%d days', $w * self::DAYS_PER_WEEK));
            $weeks[] = [
                'days' => $this->buildWeekDays($weekStart, $levelByDay, $deloadDaySet),
                'showsMonthLabel' => $this->startsLabelledMonth($weekStart, 0 === $w),
            ];
        }

        return [
            'weeks' => $weeks,
        ];
    }

    /**
     * @param array<string, int>  $levelByDay
     * @param array<string, true> $deloadDaySet
     * @return list<array{date: \DateTimeImmutable, level: int, isDeload: bool}>
     */
    private function buildWeekDays(\DateTimeImmutable $weekStart, array $levelByDay, array $deloadDaySet): array
    {
        $days = [];

        for ($d = 0; self::DAYS_PER_WEEK > $d; $d++) {
            $day = $weekStart->modify(\sprintf('+%d days', $d));
            $dayKey = $day->format('Y-m-d');
            $days[] = [
                'date' => $day,
                'level' => $levelByDay[$dayKey] ?? self::LEVEL_REST,
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

    private function gridStartOf(\DateTimeImmutable $currentWeekStart, int $weekCount): \DateTimeImmutable
    {
        return $currentWeekStart->modify(\sprintf('-%d days', ($weekCount - 1) * self::DAYS_PER_WEEK));
    }

    /**
     * Toujours sur l'année entière, même pour la grille courte du widget dashboard : un même jour
     * doit avoir la même couleur dans le widget et dans l'onglet Calendrier.
     *
     * @return array<string, float> clé `Y-m-d` => tonnage cumulé (kg), uniquement les jours avec séance
     */
    private function buildYearTonnageByDayMap(User $user, \DateTimeImmutable $currentWeekStart): array
    {
        $yearStart = $this->gridStartOf($currentWeekStart, self::FULL_YEAR_WEEKS);
        $currentWeekEnd = $currentWeekStart->modify(\sprintf('+%d days -1 second', self::DAYS_PER_WEEK));
        $tonnageByDay = [];

        foreach ($this->tonnageRepository->findTonnageSeriesByUser($user, $yearStart, $currentWeekEnd) as $row) {
            $dayKey = $row['performedAt']->format('Y-m-d');
            $tonnageByDay[$dayKey] = ($tonnageByDay[$dayKey] ?? 0.0) + $row['tonnage'];
        }

        return $tonnageByDay;
    }
}
