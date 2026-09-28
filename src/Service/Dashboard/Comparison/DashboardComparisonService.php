<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Comparison;

use App\Entity\User;
use App\Enum\Dashboard\ComparisonGranularityEnum;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Repository\WorkoutStatsRepository;
use App\Repository\WorkoutTonnageRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\Utils\WeightConverterService;
use App\Service\Workout\DeloadPeriodSetService;
use App\Service\Workout\WorkoutRecordDetectionService;

/**
 * Widget Comparaison : pour la semaine, le mois et l'année, période en cours contre les mêmes jours
 * de la période précédente (`ComparisonPeriodCalculator`) — séances, tonnage, séries, répétitions et
 * records, avec les mêmes règles de calcul que les widgets Séance et Tonnage. Les chiffres restent
 * réels même si un deload tombe dans une période : il est seulement signalé. Les données sont
 * chargées une fois pour les six périodes (`ComparisonSources`), puis additionnées en mémoire.
 */
final readonly class DashboardComparisonService
{
    public function __construct(
        private ComparisonPeriodCalculator $periodCalculator,
        private WorkoutStatsRepository $workoutStatsRepository,
        private WorkoutTonnageRepository $workoutTonnageRepository,
        private WorkoutRecordDetectionService $recordDetectionService,
        private DeloadPeriodSetService $deloadPeriodSetService,
        private WeightConverterService $weightConverter,
    ) {
    }

    /**
     * Données du propriétaire du dashboard (`$subject`), tonnage dans l'unité de celui qui regarde.
     *
     * @return list<ComparisonView>
     */
    public function build(User $subject, User $viewer): array
    {
        $now = new \DateTimeImmutable();
        $periodsByGranularity = array_map(
            fn (ComparisonGranularityEnum $granularity): ComparisonPeriods => $this->periodCalculator->calculate($granularity, $now),
            ComparisonGranularityEnum::cases(),
        );
        $sources = $this->loadSources($subject, $periodsByGranularity, $now);

        return array_map(
            fn (ComparisonGranularityEnum $granularity, ComparisonPeriods $periods): ComparisonView => $this->compare($granularity, $periods, $sources, $viewer->unitOfMeasure),
            ComparisonGranularityEnum::cases(),
            $periodsByGranularity,
        );
    }

    /**
     * Une seule plage pour les six périodes : du début de la plus ancienne (l'année précédente) à
     * aujourd'hui.
     *
     * @param list<ComparisonPeriods> $periodsByGranularity
     */
    private function loadSources(User $subject, array $periodsByGranularity, \DateTimeImmutable $now): ComparisonSources
    {
        $start = array_reduce(
            $periodsByGranularity,
            static fn (\DateTimeImmutable $earliest, ComparisonPeriods $periods): \DateTimeImmutable => min($earliest, $periods->previous->start),
            $now,
        );
        $end = $now->setTime(23, 59, 59);

        return new ComparisonSources(
            $this->workoutStatsRepository->findSetAndRepTotalsPerWorkout($subject, $start, $end),
            $this->workoutTonnageRepository->findTonnageSeriesByUser($subject, $start, $end),
            $this->recordDetectionService->findPrEvents($subject),
            $this->deloadPeriodSetService->dayKeySet($subject),
        );
    }

    private function compare(ComparisonGranularityEnum $granularity, ComparisonPeriods $periods, ComparisonSources $sources, UnitOfMeasureEnum $unit): ComparisonView
    {
        $current = $this->metricsOf($periods->current, $sources, $unit);
        $previous = $this->metricsOf($periods->previous, $sources, $unit);

        return new ComparisonView(
            $granularity,
            $periods,
            array_map(
                static fn (string $key): MetricComparison => new MetricComparison($key, $current[$key], $previous[$key]),
                array_keys($current),
            ),
            $unit->value,
            0.0 < $previous['sessions'],
            $sources->includesDeload($periods->current),
            $sources->includesDeload($periods->previous),
        );
    }

    /**
     * @return array{sessions: float, tonnage: float, sets: float, reps: float, prs: float} dans l'ordre d'affichage
     */
    private function metricsOf(DashboardPeriod $period, ComparisonSources $sources, UnitOfMeasureEnum $unit): array
    {
        return [
            'sessions' => (float) $sources->sessionCountIn($period),
            'tonnage' => $this->weightConverter->convertToLbs($sources->tonnageKgIn($period), $unit),
            'sets' => (float) $sources->setCountIn($period),
            'reps' => (float) $sources->repCountIn($period),
            'prs' => (float) $sources->prCountIn($period),
        ];
    }
}
