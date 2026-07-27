<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Service\Dashboard\DashboardTonnageChartBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Chartjs\Builder\ChartBuilder;

final class DashboardTonnageChartBuilderTest extends TestCase
{
    public function testBuildFillsLabelsAndValuesOnASingleDataset(): void
    {
        $builder = new DashboardTonnageChartBuilder(new ChartBuilder());

        $chart = $builder->build(['Lun', 'Mar'], [100.0, 150.5], 'kg');

        $data = $chart->getData();
        self::assertSame(['Lun', 'Mar'], $data['labels']);
        self::assertSame([100.0, 150.5], $data['datasets'][0]['data']);
    }

    public function testBuildSetsTheUnitLabelOnTheYAxisTitle(): void
    {
        $builder = new DashboardTonnageChartBuilder(new ChartBuilder());

        $chart = $builder->build([], [], 'lbs');

        $options = $chart->getOptions();
        self::assertSame('lbs', $options['scales']['y']['title']['text']);
    }

    public function testBuildDisablesTheLegend(): void
    {
        $builder = new DashboardTonnageChartBuilder(new ChartBuilder());

        $chart = $builder->build([], [], 'kg');

        $options = $chart->getOptions();
        self::assertFalse($options['plugins']['legend']['display']);
    }

    public function testBuildProducesTheExactExpectedDataAndOptionsStructure(): void
    {
        $builder = new DashboardTonnageChartBuilder(new ChartBuilder());

        $chart = $builder->build(['Lun', 'Mar'], [100.0, 150.5], 'kg');

        self::assertSame([
            'labels' => ['Lun', 'Mar'],
            'datasets' => [
                [
                    'data' => [100.0, 150.5],
                    'backgroundColor' => '#f43f5e',
                    'borderRadius' => 4,
                    'borderSkipped' => false,
                ],
            ],
        ], $chart->getData());

        self::assertSame([
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
                        'text' => 'kg',
                        'color' => '#4a5568',
                        'font' => [
                            'size' => 10,
                        ],
                    ],
                ],
            ],
        ], $chart->getOptions());
    }
}
