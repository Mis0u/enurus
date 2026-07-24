<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Admin;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Doughnut de répartition par genre — identité à 2 catégories, 2 premières teintes du set
 * catégoriel validé (cf. AdminLocaleChartBuilder). Volontairement pas de rose/bleu genré :
 * les 2 premiers slots neutres de l'ordre validé suffisent à 2 catégories.
 */
final readonly class AdminGenderChartBuilder
{
    private const array COLORS = ['#2a78d6', '#eb6834'];

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
        $chart = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values,
                    'backgroundColor' => self::COLORS,
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
                    'position' => 'bottom',
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
