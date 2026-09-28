<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Workout;

use App\Service\Workout\HeatmapLevelCalculator;
use PHPUnit\Framework\TestCase;

final class HeatmapLevelCalculatorTest extends TestCase
{
    public function testNoActiveDayGivesNoLevel(): void
    {
        self::assertSame([], (new HeatmapLevelCalculator())->levelByDay([]));
    }

    public function testDaysAreSplitIntoThirdsOfTheUserOwnTonnages(): void
    {
        $levels = (new HeatmapLevelCalculator())->levelByDay([
            '2026-09-01' => 1000.0,
            '2026-09-02' => 6000.0,
            '2026-09-03' => 2000.0,
            '2026-09-04' => 4000.0,
            '2026-09-05' => 3000.0,
            '2026-09-06' => 5000.0,
        ]);

        self::assertSame([
            '2026-09-01' => 1,
            '2026-09-02' => 3,
            '2026-09-03' => 1,
            '2026-09-04' => 2,
            '2026-09-05' => 2,
            '2026-09-06' => 3,
        ], $levels);
    }

    /**
     * Séance au poids du corps, gainage sans lest, cardio en distance : tonnage nul par nature,
     * la séance a pourtant bien eu lieu — elle ne doit jamais se confondre avec un jour de repos.
     */
    public function testDayWithZeroTonnageIsStillVisibleAsLight(): void
    {
        $levels = (new HeatmapLevelCalculator())->levelByDay([
            '2026-09-01' => 0.0,
            '2026-09-02' => 1000.0,
            '2026-09-03' => 2000.0,
            '2026-09-04' => 3000.0,
        ]);

        self::assertSame(1, $levels['2026-09-01']);
        self::assertSame(1, $levels['2026-09-02']);
        self::assertSame(3, $levels['2026-09-04']);
    }

    public function testDaysWithTheSameTonnageShareTheSameLevel(): void
    {
        $levels = (new HeatmapLevelCalculator())->levelByDay([
            '2026-09-01' => 2000.0,
            '2026-09-02' => 2000.0,
            '2026-09-03' => 2000.0,
        ]);

        self::assertCount(1, array_unique($levels));
    }

    /**
     * En dessous de 3 jours avec tonnage, un découpage en tiers n'a pas de sens (la seule séance
     * de l'utilisateur apparaîtrait « légère ») : niveau intermédiaire neutre.
     */
    public function testTooFewDaysToCompareGiveTheMediumLevel(): void
    {
        $levels = (new HeatmapLevelCalculator())->levelByDay([
            '2026-09-01' => 1000.0,
            '2026-09-02' => 8000.0,
        ]);

        self::assertSame([
            '2026-09-01' => 2,
            '2026-09-02' => 2,
        ], $levels);
    }
}
