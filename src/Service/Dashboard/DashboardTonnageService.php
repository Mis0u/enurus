<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Repository\WorkoutRepository;
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
        private WorkoutRepository $workoutRepository,
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
        $series = $this->workoutRepository->findTonnageSeriesByUser($user, $year->start, $year->end);

        $annualTotal = $this->weightConverter->convertToLbs(
            array_sum(array_column($series, 'tonnage')),
            $unit,
        );

        $dailyPoints = $this->buildDailyPoints($series, $year->start, $year->end, $unit, $user->locale);
        $weeklyPoints = $this->buildWeeklyPoints($series, $year->start, $year->end, $unit, $user->locale);
        $monthlyPoints = $this->buildMonthlyPoints($series, $year->start, $year->end, $unit, $user->locale);

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
     * Zero-fill jour par jour de $start à $end (bornes incluses) — une barre par jour même sans
     * séance, les séances multiples du même jour sont agrégées sur une seule barre.
     *
     * @param array<int, array{performedAt: \DateTimeImmutable, tonnage: float}> $series
     * @return array<int, array{label: string, value: float}>
     */
    private function buildDailyPoints(
        array $series,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        UnitOfMeasureEnum $unit,
        string $locale,
    ): array {
        $dailyTotals = [];
        foreach ($series as $point) {
            $key = $point['performedAt']->format('Y-m-d');
            $dailyTotals[$key] = ($dailyTotals[$key] ?? 0.0) + $point['tonnage'];
        }

        $formatter = $this->buildFormatter($locale, 'd MMM');
        $points = [];

        $cursor = $start;
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $points[] = [
                'label' => $this->formatDate($formatter, $cursor),
                'value' => $this->weightConverter->convertToLbs($dailyTotals[$key] ?? 0.0, $unit),
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return $points;
    }

    /**
     * Zero-fill semaine par semaine (lundi → dimanche) de la semaine de $start à celle de $end.
     *
     * @param array<int, array{performedAt: \DateTimeImmutable, tonnage: float}> $series
     * @return array<int, array{label: string, value: float}>
     */
    private function buildWeeklyPoints(
        array $series,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        UnitOfMeasureEnum $unit,
        string $locale,
    ): array {
        $weeklyTotals = [];
        foreach ($series as $point) {
            $key = $this->periodCalculator->weekStartOf($point['performedAt'])->format('Y-m-d');
            $weeklyTotals[$key] = ($weeklyTotals[$key] ?? 0.0) + $point['tonnage'];
        }

        $formatter = $this->buildFormatter($locale, 'd MMM');
        $points = [];

        $cursor = $this->periodCalculator->weekStartOf($start);
        $lastWeekStart = $this->periodCalculator->weekStartOf($end);
        while ($cursor <= $lastWeekStart) {
            $key = $cursor->format('Y-m-d');
            $points[] = [
                'label' => $this->formatDate($formatter, $cursor),
                'value' => $this->weightConverter->convertToLbs($weeklyTotals[$key] ?? 0.0, $unit),
            ];
            $cursor = $cursor->modify('+7 days');
        }

        return $points;
    }

    /**
     * Zero-fill mois par mois du mois de $start à celui de $end.
     *
     * @param array<int, array{performedAt: \DateTimeImmutable, tonnage: float}> $series
     * @return array<int, array{label: string, value: float}>
     */
    private function buildMonthlyPoints(
        array $series,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        UnitOfMeasureEnum $unit,
        string $locale,
    ): array {
        $monthlyTotals = [];
        foreach ($series as $point) {
            $key = $point['performedAt']->format('Y-m');
            $monthlyTotals[$key] = ($monthlyTotals[$key] ?? 0.0) + $point['tonnage'];
        }

        $formatter = $this->buildFormatter($locale, 'MMM');
        $points = [];

        $cursor = $start->modify('first day of this month')->setTime(0, 0, 0);
        $lastMonthStart = $end->modify('first day of this month')->setTime(0, 0, 0);
        while ($cursor <= $lastMonthStart) {
            $key = $cursor->format('Y-m');
            $points[] = [
                'label' => $this->formatDate($formatter, $cursor),
                'value' => $this->weightConverter->convertToLbs($monthlyTotals[$key] ?? 0.0, $unit),
            ];
            $cursor = $cursor->modify('+1 month');
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
