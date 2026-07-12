<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

readonly class DashboardTonnageChartBuilder
{
    public function __construct(
        private ChartBuilderInterface $chartBuilder,
    ) {
    }

    /**
     * @param string[] $labels
     * @param float[]  $values
     */
    public function build(array $labels, array $values, string $unitLabel): Chart
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
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'color' => 'rgba(255,255,255,0.05)',
                    ],
                    'ticks' => [
                        'color' => '#4a5568',
                        'font' => [
                            'size' => 10,
                        ],
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => 'rgba(255,255,255,0.05)',
                    ],
                    'ticks' => [
                        'color' => '#4a5568',
                        'font' => [
                            'size' => 10,
                        ],
                    ],
                    'title' => [
                        'display' => true,
                        'text' => $unitLabel,
                        'color' => '#4a5568',
                        'font' => [
                            'size' => 10,
                        ],
                    ],
                ],
            ],
        ]);

        return $chart;
    }
}
