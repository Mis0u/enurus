<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Admin;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Barres verticales de participation (%) par langue — même palette catégorielle que
 * AdminLocaleChartBuilder (8 teintes colorblind-safe déjà validées), une couleur par barre plutôt
 * qu'une seule teinte pour garder l'identité de chaque langue reconnaissable.
 */
final readonly class ContactPollLocaleParticipationChartBuilder
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
     * @param string[] $labels
     * @param float[]  $percentages
     */
    public function build(array $labels, array $percentages): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $percentages,
                    'backgroundColor' => \array_slice(self::COLORS, 0, \count($labels)),
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
                    'max' => 100,
                    'ticks' => [
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
