<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Repository\WorkoutTonnageRepository;
use App\Service\Utils\WeightConverterService;
use Symfony\UX\Chartjs\Model\Chart;

final readonly class DashboardTonnageService
{
    /**
     * Largeur en pixels réservée à chaque barre du filtre "Séances" — sert à calculer la largeur
     * minimale du conteneur scrollable (jusqu'à ~365 barres sur une année complète).
     */
    private const int DAILY_BAR_WIDTH_PX = 28;

    /**
     * Largeur en pixels réservée à chaque barre du filtre "Semaine" — un utilisateur actif toutes
     * les semaines de l'année produit jusqu'à 52 barres, également scrollables.
     */
    private const int WEEKLY_BAR_WIDTH_PX = 40;

    public function __construct(
        private WorkoutTonnageRepository $workoutTonnageRepository,
        private WeightConverterService $weightConverter,
        private DashboardTonnageChartBuilder $chartBuilder,
        private DashboardPeriodCalculator $periodCalculator,
    ) {
    }

    /**
     * @return array{
     *     unit: string,
     *     year: int,
     *     annualTotal: float,
     *     sessionsChartMinWidth: int,
     *     weekChartMinWidth: int,
     *     charts: array{sessions: Chart, week: Chart, month: Chart}
     * }
     */
    public function getData(User $user): array
    {
        $now = new \DateTimeImmutable();
        $year = $this->periodCalculator->currentYearElapsed($now);

        $unit = $user->unitOfMeasure;
        $series = $this->workoutTonnageRepository->findTonnageSeriesByUser($user, $year->start, $year->end);

        $annualTotal = $this->weightConverter->convertToLbs(
            array_sum(array_column($series, 'tonnage')),
            $unit,
        );

        $dailyPoints = $this->buildPoints(
            $series,
            $year->start,
            $year->end,
            $unit,
            $user->locale,
            'd MMM',
            static fn (\DateTimeImmutable $date): string => $date->format('Y-m-d'),
            static fn (\DateTimeImmutable $date): \DateTimeImmutable => $date,
            static fn (\DateTimeImmutable $cursor): \DateTimeImmutable => $cursor->modify('+1 day'),
        );
        $weeklyPoints = $this->buildPoints(
            $series,
            $year->start,
            $year->end,
            $unit,
            $user->locale,
            'd MMM',
            fn (\DateTimeImmutable $date): string => $this->periodCalculator->weekStartOf($date)->format('Y-m-d'),
            fn (\DateTimeImmutable $date): \DateTimeImmutable => $this->periodCalculator->weekStartOf($date),
            static fn (\DateTimeImmutable $cursor): \DateTimeImmutable => $cursor->modify('+7 days'),
        );
        $monthlyPoints = $this->buildPoints(
            $series,
            $year->start,
            $year->end,
            $unit,
            $user->locale,
            'MMM',
            static fn (\DateTimeImmutable $date): string => $date->format('Y-m'),
            static fn (\DateTimeImmutable $date): \DateTimeImmutable => $date->modify('first day of this month')->setTime(0, 0, 0),
            static fn (\DateTimeImmutable $cursor): \DateTimeImmutable => $cursor->modify('+1 month'),
        );

        return [
            'unit' => $unit->value,
            'year' => (int) $now->format('Y'),
            'annualTotal' => $annualTotal,
            'sessionsChartMinWidth' => \count($dailyPoints) * self::DAILY_BAR_WIDTH_PX,
            'weekChartMinWidth' => \count($weeklyPoints) * self::WEEKLY_BAR_WIDTH_PX,
            'charts' => [
                'sessions' => $this->buildChart($dailyPoints, $unit),
                'week' => $this->buildChart($weeklyPoints, $unit),
                'month' => $this->buildChart($monthlyPoints, $unit),
            ],
        ];
    }

    /**
     * Zero-fill générique (jour/semaine/mois) : regroupe $series par clé de bucket
     * ($bucketKeyOf), puis parcourt le curseur de $start à $end (bornes normalisées par
     * $normalizeBucketStart, incrémentées par $incrementCursor) — une barre par bucket même sans
     * séance, les séances multiples du même bucket sont agrégées sur une seule barre.
     *
     * @param array<int, array{performedAt: \DateTimeImmutable, tonnage: float}> $series
     * @param \Closure(\DateTimeImmutable): string $bucketKeyOf clé de regroupement d'une date
     * @param \Closure(\DateTimeImmutable): \DateTimeImmutable $normalizeBucketStart aligne une date sur le début de son bucket
     * @param \Closure(\DateTimeImmutable): \DateTimeImmutable $incrementCursor avance le curseur d'un bucket
     * @return array<int, array{label: string, value: float}>
     */
    private function buildPoints(
        array $series,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        UnitOfMeasureEnum $unit,
        string $locale,
        string $labelPattern,
        \Closure $bucketKeyOf,
        \Closure $normalizeBucketStart,
        \Closure $incrementCursor,
    ): array {
        $totals = [];
        foreach ($series as $point) {
            $key = $bucketKeyOf($point['performedAt']);
            $totals[$key] = ($totals[$key] ?? 0.0) + $point['tonnage'];
        }

        $formatter = $this->buildFormatter($locale, $labelPattern);
        $points = [];

        $cursor = $normalizeBucketStart($start);
        $lastBucketStart = $normalizeBucketStart($end);
        while ($cursor <= $lastBucketStart) {
            $key = $bucketKeyOf($cursor);
            $points[] = [
                'label' => $this->formatDate($formatter, $cursor),
                'value' => $this->weightConverter->convertToLbs($totals[$key] ?? 0.0, $unit),
            ];
            $cursor = $incrementCursor($cursor);
        }

        return $points;
    }

    private function buildFormatter(string $locale, string $pattern): \IntlDateFormatter
    {
        return new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            null,
            null,
            $pattern,
        );
    }

    private function formatDate(\IntlDateFormatter $formatter, \DateTimeImmutable $date): string
    {
        $formatted = $formatter->format($date);

        if (false === $formatted) {
            throw new \LogicException('Failed to format chart date label.');
        }

        return $formatted;
    }

    /**
     * @param array<int, array{label: string, value: float}> $points
     */
    private function buildChart(array $points, UnitOfMeasureEnum $unit): Chart
    {
        $labels = array_column($points, 'label');
        /** @var float[] $values */
        $values = array_column($points, 'value');

        return $this->chartBuilder->build($labels, $values, $unit->value);
    }
}
