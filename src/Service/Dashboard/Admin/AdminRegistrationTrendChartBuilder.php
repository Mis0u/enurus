<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Admin;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Courbe de tendance des inscriptions — une seule série (magnitude dans le temps), même teinte
 * unique que les autres widgets admin plutôt qu'une palette catégorielle.
 */
final readonly class AdminRegistrationTrendChartBuilder
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
        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values,
                    'borderColor' => '#f43f5e',
                    'backgroundColor' => 'rgba(244, 63, 94, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 3,
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
                    'ticks' => [
                        'color' => '#4a5568',
                        'font' => [
                            'size' => 10,
                        ],
                    ],
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                        'color' => '#4a5568',
                    ],
                    'grid' => [
                        'color' => 'rgba(0,0,0,0.05)',
                    ],
                ],
            ],
        ]);

        return $chart;
    }
}
