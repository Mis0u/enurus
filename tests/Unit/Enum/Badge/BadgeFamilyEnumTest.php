<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum\Badge;

use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BadgeFamilyEnumTest extends TestCase
{
    /**
     * @return iterable<string, array{BadgeFamilyEnum}>
     */
    public static function milestoneFamilyProvider(): iterable
    {
        foreach (BadgeFamilyEnum::milestoneFamilies() as $family) {
            yield $family->value => [$family];
        }
    }

    #[DataProvider('milestoneFamilyProvider')]
    public function testEveryMilestoneFamilyHasOneThresholdPerTier(BadgeFamilyEnum $family): void
    {
        self::assertCount(\count(BadgeTierEnum::cases()), $family->thresholds());
    }

    #[DataProvider('milestoneFamilyProvider')]
    public function testThresholdsAreStrictlyAscending(BadgeFamilyEnum $family): void
    {
        $thresholds = $family->thresholds();
        $sorted = $thresholds;
        sort($sorted);

        self::assertSame($sorted, $thresholds);
        self::assertSame(\count($thresholds), \count(array_unique($thresholds)));
    }

    public function testThresholdOfMatchesTierPosition(): void
    {
        self::assertSame(1, BadgeFamilyEnum::ASSIDUITY->thresholdOf(BadgeTierEnum::BRONZE));
        self::assertSame(1000, BadgeFamilyEnum::ASSIDUITY->thresholdOf(BadgeTierEnum::RUBY));
        self::assertSame(60, BadgeFamilyEnum::SENIORITY->thresholdOf(BadgeTierEnum::RUBY));
        self::assertSame(10000, BadgeFamilyEnum::TONNAGE->thresholdOf(BadgeTierEnum::RUBY));
    }

    public function testLegendHasNoThreshold(): void
    {
        self::assertSame([], BadgeFamilyEnum::LEGEND->thresholds());
        self::assertNotContains(BadgeFamilyEnum::LEGEND, BadgeFamilyEnum::milestoneFamilies());

        $this->expectException(\LogicException::class);
        BadgeFamilyEnum::LEGEND->thresholdOf(BadgeTierEnum::RUBY);
    }

    public function testOnlySeniorityIsNeverRevoked(): void
    {
        self::assertFalse(BadgeFamilyEnum::SENIORITY->isRevocable());
        self::assertTrue(BadgeFamilyEnum::ASSIDUITY->isRevocable());
        self::assertTrue(BadgeFamilyEnum::REGULARITY->isRevocable());
        self::assertTrue(BadgeFamilyEnum::TONNAGE->isRevocable());
        self::assertTrue(BadgeFamilyEnum::LEGEND->isRevocable());
    }
}
