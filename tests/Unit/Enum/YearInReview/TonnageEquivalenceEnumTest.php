<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum\YearInReview;

use App\Enum\YearInReview\TonnageEquivalenceEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TonnageEquivalenceEnumTest extends TestCase
{
    /**
     * @return iterable<string, array{float, TonnageEquivalenceEnum|null}>
     */
    public static function tonnages(): iterable
    {
        yield 'too light for a piano' => [479.0, null];
        yield 'exactly one piano' => [480.0, TonnageEquivalenceEnum::GRAND_PIANO];
        yield 'many pianos, not yet an elephant' => [5999.0, TonnageEquivalenceEnum::GRAND_PIANO];
        yield 'one Big Ben bell' => [13700.0, TonnageEquivalenceEnum::BIG_BEN_BELL];
        yield 'just below an A380' => [276999.0, TonnageEquivalenceEnum::BOEING_737];
        yield 'one A380 even well above' => [312000.0, TonnageEquivalenceEnum::AIRBUS_A380];
    }

    #[DataProvider('tonnages')]
    public function testLargestObjectLiftedAtLeastOnceIsChosen(float $tonnageKg, ?TonnageEquivalenceEnum $expected): void
    {
        self::assertSame($expected, TonnageEquivalenceEnum::largestLiftedBy($tonnageKg));
    }

    public function testCountIsRoundedDownSoItNeverOverstates(): void
    {
        self::assertSame(1, TonnageEquivalenceEnum::AIRBUS_A380->timesIn(553999.0));
        self::assertSame(2, TonnageEquivalenceEnum::AIRBUS_A380->timesIn(554000.0));
    }

    public function testScaleGoesFromLightestToHeaviest(): void
    {
        $weights = array_map(static fn (TonnageEquivalenceEnum $object): float => $object->weightKg(), TonnageEquivalenceEnum::cases());
        $sortedWeights = $weights;
        sort($sortedWeights);

        self::assertSame($sortedWeights, $weights);
    }
}
