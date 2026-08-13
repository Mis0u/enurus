<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Exercise;

use App\Service\Exercise\ExerciseHistoryChartBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Chartjs\Builder\ChartBuilder;

final class ExerciseHistoryChartBuilderTest extends TestCase
{
    public function testBuildFillsLabelsAndValues(): void
    {
        $builder = new ExerciseHistoryChartBuilder(new ChartBuilder());

        $chart = $builder->build([
            [
                'label' => 'Lun',
                'value' => 100.0,
                'isPr' => true,
                'isCurrentRecord' => true,
            ],
            [
                'label' => 'Mar',
                'value' => 90.0,
                'isPr' => false,
                'isCurrentRecord' => false,
            ],
        ], 'kg');

        $data = $chart->getData();
        self::assertSame(['Lun', 'Mar'], $data['labels']);
        self::assertSame([100.0, 90.0], $data['datasets'][0]['data']);
    }

    public function testBuildSetsTheUnitLabelOnTheYAxisTitle(): void
    {
        $builder = new ExerciseHistoryChartBuilder(new ChartBuilder());

        $chart = $builder->build([], 'min');

        $options = $chart->getOptions();
        self::assertSame('min', $options['scales']['y']['title']['text']);
    }

    public function testBuildDisablesTheLegend(): void
    {
        $builder = new ExerciseHistoryChartBuilder(new ChartBuilder());

        $chart = $builder->build([], 'kg');

        $options = $chart->getOptions();
        self::assertFalse($options['plugins']['legend']['display']);
    }

    public function testPrPointsAreFilledAndNonPrPointsAreHollow(): void
    {
        $builder = new ExerciseHistoryChartBuilder(new ChartBuilder());

        $chart = $builder->build([
            [
                'label' => 'Lun',
                'value' => 100.0,
                'isPr' => true,
                'isCurrentRecord' => false,
            ],
            [
                'label' => 'Mar',
                'value' => 90.0,
                'isPr' => false,
                'isCurrentRecord' => false,
            ],
        ], 'kg');

        $dataset = $chart->getData()['datasets'][0];
        self::assertSame('#f43f5e', $dataset['pointBackgroundColor'][0]);
        self::assertSame('transparent', $dataset['pointBackgroundColor'][1]);
    }

    public function testTheCurrentRecordPointHasALargerRadiusAndThickerBorderThanOtherPrPoints(): void
    {
        $builder = new ExerciseHistoryChartBuilder(new ChartBuilder());

        $chart = $builder->build([
            [
                'label' => 'Lun',
                'value' => 90.0,
                'isPr' => true,
                'isCurrentRecord' => false,
            ],
            [
                'label' => 'Mar',
                'value' => 100.0,
                'isPr' => true,
                'isCurrentRecord' => true,
            ],
        ], 'kg');

        $dataset = $chart->getData()['datasets'][0];
        self::assertGreaterThan($dataset['pointRadius'][0], $dataset['pointRadius'][1]);
        self::assertGreaterThan($dataset['pointBorderWidth'][0], $dataset['pointBorderWidth'][1]);
    }
}
