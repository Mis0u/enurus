<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Admin;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Barres horizontales de répartition par unité de mesure — même teinte unique que
 * `DashboardTonnageChartBuilder` (magnitude, pas identité : chaque barre porte déjà son
 * étiquette d'axe, pas besoin de palette catégorielle).
 */
final readonly class AdminUnitOfMeasureChartBuilder
{
    public function __construct(
        private ChartBuilderInterface $chartBuilder,
    ) {
    }

    /**
     * @param string[] $labels
     * @param int[]    $values
     */
    public function build(array $labels, array $values): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values,
                    'backgroundColor' => '#f43f5e',
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
            ],
        ]);

        $chart->setOptions([
            'indexAxis' => 'y',
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                        'color' => '#4a5568',
                    ],
                    'grid' => [
                        'color' => 'rgba(255,255,255,0.05)',
                    ],
                ],
                'y' => [
                    'ticks' => [
                        'color' => '#4a5568',
                    ],
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ]);

        return $chart;
    }
}
