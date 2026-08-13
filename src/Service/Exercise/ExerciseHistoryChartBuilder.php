<?php

declare(strict_types=1);

namespace App\Service\Exercise;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final readonly class ExerciseHistoryChartBuilder
{
    private const string RECORD_COLOR = '#f43f5e';

    public function __construct(
        private ChartBuilderInterface $chartBuilder,
    ) {
    }

    /**
     * Courbe de progression : points PR pleins, points intermédiaires creux, record actuel
     * distingué par un rayon/contour plus marqué (le "halo" du cahier des charges — Chart.js ne
     * propose pas de halo natif, un point plus large avec un contour épais rend le même signal).
     *
     * @param array<int, array{label: string, value: float, isPr: bool, isCurrentRecord: bool}> $points
     */
    public function build(array $points, string $unitLabel): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        $labels = array_column($points, 'label');
        /** @var float[] $values */
        $values = array_column($points, 'value');

        $chart->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values,
                    'borderColor' => self::RECORD_COLOR,
                    'backgroundColor' => self::RECORD_COLOR,
                    'tension' => 0.3,
                    'pointRadius' => array_map($this->pointRadius(...), $points),
                    'pointBorderWidth' => array_map($this->pointBorderWidth(...), $points),
                    'pointBackgroundColor' => array_map($this->pointBackgroundColor(...), $points),
                    'pointBorderColor' => self::RECORD_COLOR,
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
                    'beginAtZero' => false,
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

    /**
     * @param array{label: string, value: float, isPr: bool, isCurrentRecord: bool} $point
     */
    private function pointRadius(array $point): int
    {
        return match (true) {
            $point['isCurrentRecord'] => 7,
            $point['isPr'] => 5,
            default => 3,
        };
    }

    /**
     * @param array{label: string, value: float, isPr: bool, isCurrentRecord: bool} $point
     */
    private function pointBorderWidth(array $point): int
    {
        return $point['isCurrentRecord'] ? 3 : 2;
    }

    /**
     * @param array{label: string, value: float, isPr: bool, isCurrentRecord: bool} $point
     */
    private function pointBackgroundColor(array $point): string
    {
        return $point['isPr'] ? self::RECORD_COLOR : 'transparent';
    }
}
