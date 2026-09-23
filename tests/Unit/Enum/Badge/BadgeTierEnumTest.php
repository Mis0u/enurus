<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum\Badge;

use App\Enum\Badge\BadgeTierEnum;
use PHPUnit\Framework\TestCase;

final class BadgeTierEnumTest extends TestCase
{
    public function testBronzeIsBareShield(): void
    {
        $tier = BadgeTierEnum::BRONZE;

        self::assertFalse($tier->hasRibbonTails());
        self::assertFalse($tier->hasRivets());
        self::assertFalse($tier->hasSpeedStreaks());
        self::assertFalse($tier->hasLaurels());
        self::assertFalse($tier->hasCrown());
    }

    public function testEachTierAddsItsOwnOrnament(): void
    {
        self::assertTrue(BadgeTierEnum::SILVER->hasRivets());
        self::assertFalse(BadgeTierEnum::SILVER->hasSpeedStreaks());

        self::assertTrue(BadgeTierEnum::GOLD->hasSpeedStreaks());
        self::assertFalse(BadgeTierEnum::GOLD->hasLaurels());

        self::assertTrue(BadgeTierEnum::PLATINUM->hasLaurels());
        self::assertFalse(BadgeTierEnum::PLATINUM->hasCrown());

        self::assertTrue(BadgeTierEnum::EMERALD->hasCrown());
        self::assertFalse(BadgeTierEnum::EMERALD->hasDoubleStreaks());

        self::assertTrue(BadgeTierEnum::RUBY->hasDoubleStreaks());
    }

    public function testOrnamentsAccumulate(): void
    {
        $ruby = BadgeTierEnum::RUBY;

        self::assertTrue($ruby->hasRibbonTails());
        self::assertTrue($ruby->hasRivets());
        self::assertTrue($ruby->hasSpeedStreaks());
        self::assertTrue($ruby->hasSparkle());
        self::assertTrue($ruby->hasLaurels());
        self::assertTrue($ruby->hasGlow());
        self::assertTrue($ruby->hasCrown());
    }
}
