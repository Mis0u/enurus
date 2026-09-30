<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Service\Dashboard\DashboardPeriod;
use App\Service\Workout\HeatmapLevelCalculator;

/**
 * Grille heatmap de l'écran du résumé annuel, au format HeatmapData de l'onglet Calendrier :
 * semaines entières du lundi au dimanche, de la semaine du 1er janvier à celle du 15 décembre. Les
 * niveaux sont relatifs aux seules séances de l'année figée ; les deloads ne sont pas figés, donc
 * jamais marqués ici. Sur un écran de téléphone, seul le début de chaque trimestre porte son mois
 * (douze libellés se chevaucheraient).
 *
 * @phpstan-import-type HeatmapData from \App\Service\Workout\WorkoutHeatmapService
 */
final readonly class YearInReviewHeatmapGridBuilder
{
    private const int DAYS_PER_WEEK = 7;

    private const int LEVEL_REST = 0;

    private const int MONTHS_PER_LABEL = 3;

    public function __construct(
        private HeatmapLevelCalculator $levelCalculator,
    ) {
    }

    /**
     * @param array<string, float> $tonnageKgByDay
     *
     * @return HeatmapData
     */
    public function build(array $tonnageKgByDay, DashboardPeriod $period): array
    {
        $levelByDay = $this->levelCalculator->levelByDay($tonnageKgByDay);
        $lastMonday = $this->mondayOf($period->end);
        $weeks = [];

        for ($monday = $this->mondayOf($period->start); $monday <= $lastMonday; $monday = $monday->modify('+1 week')) {
            $weeks[] = [
                'days' => $this->weekDays($monday, $levelByDay),
                'showsMonthLabel' => [] !== $weeks && $this->startsLabelledQuarter($monday),
            ];
        }

        return [
            'weeks' => $weeks,
        ];
    }

    /**
     * @param array<string, int> $levelByDay
     *
     * @return list<array{date: \DateTimeImmutable, level: int, isDeload: bool}>
     */
    private function weekDays(\DateTimeImmutable $monday, array $levelByDay): array
    {
        $days = [];

        for ($offset = 0; self::DAYS_PER_WEEK > $offset; ++$offset) {
            $day = $monday->modify(\sprintf('+%d days', $offset));
            $days[] = [
                'date' => $day,
                'level' => $levelByDay[$day->format('Y-m-d')] ?? self::LEVEL_REST,
                'isDeload' => false,
            ];
        }

        return $days;
    }

    private function startsLabelledQuarter(\DateTimeImmutable $monday): bool
    {
        $month = (int) $monday->format('n');

        return $monday->format('m') !== $monday->modify('-1 week')->format('m') && 1 === $month % self::MONTHS_PER_LABEL;
    }

    private function mondayOf(\DateTimeImmutable $day): \DateTimeImmutable
    {
        return $day->setTime(0, 0)->modify(\sprintf('-%d days', (int) $day->format('N') - 1));
    }
}
