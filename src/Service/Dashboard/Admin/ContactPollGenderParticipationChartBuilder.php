<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Admin;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Camembert de participation (%) par genre — contrairement à AdminGenderChartBuilder (neutre,
 * volontairement pas de rose/cyan genré pour les stats d'inscription), ce widget-ci reprend
 * délibérément l'identité rose/cyan homme/femme déjà posée sur la page d'inscription
 * (registration/theme/gender_theme.html.twig), à la demande explicite du produit.
 */
final readonly class ContactPollGenderParticipationChartBuilder
{
    private const array COLORS = [
        '#06b6d4', // cyan — homme
        '#f43f5e', // rose — femme
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
        $chart = $this->chartBuilder->createChart(Chart::TYPE_PIE);

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $percentages,
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
