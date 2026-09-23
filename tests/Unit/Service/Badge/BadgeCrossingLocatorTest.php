<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Badge;

use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Service\Badge\BadgeCrossing;
use App\Service\Badge\BadgeCrossingLocator;
use App\Service\Badge\BadgeKey;
use App\Service\Badge\WorkoutTimelineEntry;
use App\Service\Workout\WeeklyStreakCalculator;
use PHPUnit\Framework\TestCase;

final class BadgeCrossingLocatorTest extends TestCase
{
    private const string NOW = '2026-09-23 10:00:00';

    private const string REGISTERED_AT = '2026-01-15 09:00:00';

    public function testAssiduityIsCrossedByTheNthWorkoutInChronologicalOrder(): void
    {
        $crossing = $this->located(new BadgeKey(BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::SILVER), $this->weeklyTimeline(12));

        self::assertSame('w10', $crossing->workoutId);
        self::assertSame('2026-06-08', $crossing->date->format('Y-m-d'));
    }

    public function testAssiduityNotYetReachedHasNoCrossing(): void
    {
        self::assertNull($this->locate(new BadgeKey(BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::SILVER), $this->weeklyTimeline(9)));
    }

    public function testTonnageIsCrossedWhenTheCumulativeTotalReachesTheThreshold(): void
    {
        // 3 000 kg par séance : 10 t atteintes à la 4ᵉ séance (12 000 kg).
        $crossing = $this->located(new BadgeKey(BadgeFamilyEnum::TONNAGE, BadgeTierEnum::BRONZE), $this->weeklyTimeline(6, tonnageKg: 3000.0));

        self::assertSame('w4', $crossing->workoutId);
    }

    public function testRegularityIsCrossedByTheFirstWorkoutOfTheWeekCompletingTheStreak(): void
    {
        // Une séance par semaine depuis le lundi 06/04 : la 4ᵉ semaine consécutive est celle du 27/04.
        $crossing = $this->located(new BadgeKey(BadgeFamilyEnum::REGULARITY, BadgeTierEnum::BRONZE), $this->weeklyTimeline(6));

        self::assertSame('w4', $crossing->workoutId);
        self::assertSame('2026-04-27', $crossing->date->format('Y-m-d'));
    }

    public function testSeniorityIsReachedAtTheAnniversaryWithoutAnyWorkout(): void
    {
        $crossing = $this->located(new BadgeKey(BadgeFamilyEnum::SENIORITY, BadgeTierEnum::SILVER), []);

        self::assertNull($crossing->workoutId);
        self::assertSame('2026-07-15', $crossing->date->format('Y-m-d'));
    }

    public function testSeniorityNotYetReachedHasNoCrossing(): void
    {
        self::assertNull($this->locate(new BadgeKey(BadgeFamilyEnum::SENIORITY, BadgeTierEnum::GOLD), []));
    }

    public function testLegendHasNoCrossingUnlessEveryRubyIsReached(): void
    {
        self::assertNull($this->locate(BadgeKey::legend(), $this->weeklyTimeline(20)));
    }

    /**
     * Une séance par semaine, le lundi à 18h, à partir du 06/04/2026 : w1, w2, …
     *
     * @return list<WorkoutTimelineEntry>
     */
    private function weeklyTimeline(int $count, float $tonnageKg = 1000.0): array
    {
        $timeline = [];
        $monday = new \DateTimeImmutable('2026-04-06 18:00:00');

        for ($i = 1; $i <= $count; $i++) {
            $timeline[] = new WorkoutTimelineEntry('w' . $i, $monday->modify(\sprintf('+%d weeks', $i - 1)), $tonnageKg);
        }

        return $timeline;
    }

    /**
     * @param list<WorkoutTimelineEntry> $timeline
     */
    private function located(BadgeKey $key, array $timeline): BadgeCrossing
    {
        return $this->locate($key, $timeline) ?? throw new \LogicException('Expected a crossing.');
    }

    /**
     * @param list<WorkoutTimelineEntry> $timeline
     */
    private function locate(BadgeKey $key, array $timeline): ?BadgeCrossing
    {
        return (new BadgeCrossingLocator(new WeeklyStreakCalculator()))->locate(
            $key,
            $timeline,
            [],
            new \DateTimeImmutable(self::REGISTERED_AT),
            new \DateTimeImmutable(self::NOW),
        );
    }
}
