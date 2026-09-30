<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Repository\WorkoutTonnageRepository;
use App\Service\Dashboard\DashboardPeriod;

/**
 * Tonnage brut par jour (kg) : les niveaux de couleur sont calculés à l'affichage, comme pour la
 * heatmap de l'onglet Calendrier.
 */
final readonly class YearInReviewHeatmapCalculator
{
    private const string DAY_FORMAT = 'Y-m-d';

    public function __construct(
        private WorkoutTonnageRepository $workoutTonnageRepository,
    ) {
    }

    /**
     * @return array<string, float> jour `Y-m-d` => tonnage en kg
     */
    public function calculate(User $user, DashboardPeriod $period): array
    {
        $tonnageKgByDay = [];

        foreach ($this->workoutTonnageRepository->findTonnageSeriesByUser($user, $period->start, $period->end) as $row) {
            $day = $row['performedAt']->format(self::DAY_FORMAT);
            $tonnageKgByDay[$day] = ($tonnageKgByDay[$day] ?? 0.0) + $row['tonnage'];
        }

        return $tonnageKgByDay;
    }
}
