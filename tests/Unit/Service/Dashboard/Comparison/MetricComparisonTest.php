<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard\Comparison;

use App\Service\Dashboard\Comparison\MetricComparison;
use PHPUnit\Framework\TestCase;

final class MetricComparisonTest extends TestCase
{
    public function testAnIncreaseGivesTheGapAndTheRoundedPercentage(): void
    {
        $comparison = new MetricComparison('sessions', current: 12, previous: 9);

        self::assertSame(3.0, $comparison->delta);
        self::assertSame(33, $comparison->percent);
        self::assertSame('up', $comparison->trend);
    }

    public function testADecreaseIsNegative(): void
    {
        $comparison = new MetricComparison('reps', current: 1480, previous: 1612);

        self::assertSame(-132.0, $comparison->delta);
        self::assertSame(-8, $comparison->percent);
        self::assertSame('down', $comparison->trend);
    }

    public function testNoChangeIsEqual(): void
    {
        self::assertSame('equal', new MetricComparison('prs', current: 2, previous: 2)->trend);
    }

    /**
     * Rien la période précédente : pas de pourcentage (« +100 % » ou infini n'aurait pas de sens).
     */
    public function testNoPercentageWhenThePreviousValueIsZero(): void
    {
        $comparison = new MetricComparison('prs', current: 3, previous: 0);

        self::assertNull($comparison->percent);
        self::assertSame(3.0, $comparison->delta);
    }
}
