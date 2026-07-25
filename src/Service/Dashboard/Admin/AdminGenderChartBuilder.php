<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Admin;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Doughnut de répartition par genre — ordre des couleurs aligné sur `GenderEnum::cases()`
 * (MALE puis FEMALE) : bleu pour les hommes, rose (identité du site) pour les femmes.
 */
final readonly class AdminGenderChartBuilder
{
    private const array COLORS = ['#2a78d6', '#f43f5e'];

    public function __construct(
        private ChartBuilderInterface $chartBuilder,
    ) {
    }

    /**
     * @param string[]    $labels
     * @param list<float> $values
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
