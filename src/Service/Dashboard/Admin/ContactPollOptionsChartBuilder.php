<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Admin;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Barres de résultats par option de sondage — nombre d'options libre (contrairement aux widgets
 * catégoriels à cardinalité fixe comme AdminLocaleChartBuilder), donc une seule teinte plutôt
 * qu'une palette catégorielle qui devrait s'étendre à la volée.
 */
final readonly class ContactPollOptionsChartBuilder
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
                    'backgroundColor' => 'rgba(244, 63, 94, 0.6)',
                    'borderColor' => '#f43f5e',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
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
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                        'color' => '#4a5568',
                    ],
                ],
                'x' => [
                    'ticks' => [
                        'color' => '#4a5568',
                        'font' => [
                            'size' => 11,
                        ],
                    ],
                ],
            ],
        ]);

        return $chart;
    }
}
