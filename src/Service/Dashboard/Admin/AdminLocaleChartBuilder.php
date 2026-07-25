<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Admin;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Camembert de répartition par langue — identité (8 catégories), donc palette catégorielle fixe
 * plutôt qu'une seule teinte. Ordre et couleurs issus du set validé colorblind-safe du design
 * system (8 teintes, ΔE CVD adjacent ≥ 8 dans les deux modes) — jamais généré/étendu à la volée.
 */
final readonly class AdminLocaleChartBuilder
{
    private const array COLORS = [
        '#2a78d6', // blue
        '#eb6834', // orange
        '#1baf7a', // aqua
        '#eda100', // yellow
        '#e87ba4', // magenta
        '#008300', // green
        '#4a3aa7', // violet
        '#e34948', // red
    ];

    public function __construct(
        private ChartBuilderInterface $chartBuilder,
    ) {
    }

    /**
     * @param string[]     $labels
     * @param list<float>  $values
     */
    public function build(array $labels, array $values): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_PIE);

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values,
                    'backgroundColor' => \array_slice(self::COLORS, 0, \count($labels)),
                    'borderWidth' => 2,
                    'borderColor' => '#fcfcfb',
                ],
            ],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                    'labels' => [
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
