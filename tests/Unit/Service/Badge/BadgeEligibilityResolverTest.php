<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Badge;

use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Service\Badge\BadgeEligibilityResolver;
use App\Service\Badge\BadgeKey;
use App\Service\Badge\BadgeProgress;
use PHPUnit\Framework\TestCase;

final class BadgeEligibilityResolverTest extends TestCase
{
    public function testNewUserWithoutWorkoutEarnsNothing(): void
    {
        self::assertSame([], $this->resolve(new BadgeProgress(0, 0, 0, 0.0)));
    }

    public function testExactThresholdUnlocksTheTier(): void
    {
        $ids = $this->resolve(new BadgeProgress(workoutCount: 10, seniorityMonths: 0, bestRegularityStreakWeeks: 0, totalTonnageKg: 0.0));

        self::assertSame(['assiduity:1', 'assiduity:2'], $ids);
    }

    public function testJustBelowThresholdDoesNotUnlock(): void
    {
        $ids = $this->resolve(new BadgeProgress(workoutCount: 9, seniorityMonths: 5, bestRegularityStreakWeeks: 3, totalTonnageKg: 9999.9));

        self::assertSame(['assiduity:1', 'seniority:1'], $ids);
    }

    public function testTonnageThresholdIsExpressedInTonnes(): void
    {
        $ids = $this->resolve(new BadgeProgress(workoutCount: 0, seniorityMonths: 0, bestRegularityStreakWeeks: 0, totalTonnageKg: 100_000.0));

        self::assertSame(['tonnage:1', 'tonnage:2'], $ids);
    }

    public function testRegularityUsesBestStreak(): void
    {
        $ids = $this->resolve(new BadgeProgress(workoutCount: 0, seniorityMonths: 0, bestRegularityStreakWeeks: 26, totalTonnageKg: 0.0));

        self::assertSame(['regularity:1', 'regularity:2', 'regularity:3'], $ids);
    }

    public function testLegendRequiresRubyInAllFourFamilies(): void
    {
        $almost = $this->resolve(new BadgeProgress(workoutCount: 1000, seniorityMonths: 60, bestRegularityStreakWeeks: 208, totalTonnageKg: 9_999_000.0));
        $all = $this->resolve(new BadgeProgress(workoutCount: 1000, seniorityMonths: 60, bestRegularityStreakWeeks: 208, totalTonnageKg: 10_000_000.0));

        self::assertNotContains('legend:6', $almost);
        self::assertContains('legend:6', $all);
        self::assertCount(25, $all);
    }

    public function testKeysAreIndexedById(): void
    {
        $keys = (new BadgeEligibilityResolver())->resolve(new BadgeProgress(1, 0, 0, 0.0));
        $key = $keys['assiduity:1'] ?? null;

        self::assertInstanceOf(BadgeKey::class, $key);
        self::assertSame(BadgeFamilyEnum::ASSIDUITY, $key->family);
        self::assertSame(BadgeTierEnum::BRONZE, $key->tier);
    }

    /**
     * @return list<string>
     */
    private function resolve(BadgeProgress $progress): array
    {
        return array_keys((new BadgeEligibilityResolver())->resolve($progress));
    }
}
