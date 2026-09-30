<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Enum\Entity\Workout\WorkoutMoodEnum;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\YearInReview\Calculator\YearInReviewMoodCalculator;
use App\Service\YearInReview\Snapshot\YearInReviewMood;
use PHPUnit\Framework\TestCase;

final class YearInReviewMoodCalculatorTest extends TestCase
{
    public function testMostFrequentMoodWithItsShareOfWorkoutsWithAMood(): void
    {
        $calculator = $this->calculator([
            'fatigue' => 10,
            'en_forme' => 23,
            'normal' => 17,
        ]);

        self::assertEquals(new YearInReviewMood(WorkoutMoodEnum::EN_FORME, 46), $calculator->calculate(new User(), $this->period()));
    }

    public function testTieGoesToTheFirstMoodOfTheEnum(): void
    {
        $calculator = $this->calculator([
            'fatigue' => 5,
            'en_forme' => 5,
        ]);

        self::assertSame(WorkoutMoodEnum::EN_FORME, $calculator->calculate(new User(), $this->period())?->mood);
    }

    public function testNoMoodWhenNoneWasEntered(): void
    {
        self::assertNull($this->calculator([])->calculate(new User(), $this->period()));
    }

    /**
     * @param array<string, int> $counts
     */
    private function calculator(array $counts): YearInReviewMoodCalculator
    {
        $statsRepository = $this->createStub(WorkoutStatsRepository::class);
        $statsRepository->method('countMoodsInRange')->willReturn($counts);

        return new YearInReviewMoodCalculator($statsRepository);
    }

    private function period(): DashboardPeriod
    {
        return new DashboardPeriod(new \DateTimeImmutable('2026-01-01 00:00:00'), new \DateTimeImmutable('2026-12-15 23:59:59'));
    }
}
