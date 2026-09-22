<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Workout;

use App\Entity\User;
use App\Repository\WorkoutRepository;
use App\Service\Dashboard\DashboardPeriodCalculator;
use App\Service\Workout\DeloadPeriodSetService;
use App\Service\Workout\WorkoutHeatmapService;
use PHPUnit\Framework\TestCase;

final class WorkoutHeatmapServiceTest extends TestCase
{
    public function testGridHasFiftyThreeWeeksOfSevenDaysEndingOnTheCurrentWeek(): void
    {
        $result = $this->build([]);

        self::assertCount(53, $result['weeks']);

        $currentWeekStart = (new DashboardPeriodCalculator())->weekStartOf(new \DateTimeImmutable());
        $lastWeek = $result['weeks'][52];

        self::assertCount(7, $lastWeek['days']);
        self::assertSame($currentWeekStart->format('Y-m-d'), $lastWeek['days'][0]['date']->format('Y-m-d'));
    }

    public function testDayWithNoSessionHasLevelZero(): void
    {
        $result = $this->build([]);

        foreach ($result['weeks'][52]['days'] as $day) {
            self::assertSame(0, $day['level']);
        }
    }

    public function testShortSessionHasLevelOne(): void
    {
        $today = new \DateTimeImmutable('today');
        $result = $this->build([[
            'performedAt' => $today,
            'duration' => 30,
        ]]);

        self::assertSame(1, $this->levelForDate($result, $today));
    }

    public function testMediumSessionHasLevelTwo(): void
    {
        $today = new \DateTimeImmutable('today');
        $result = $this->build([[
            'performedAt' => $today,
            'duration' => 60,
        ]]);

        self::assertSame(2, $this->levelForDate($result, $today));
    }

    public function testLongSessionHasLevelThree(): void
    {
        $today = new \DateTimeImmutable('today');
        $result = $this->build([[
            'performedAt' => $today,
            'duration' => 90,
        ]]);

        self::assertSame(3, $this->levelForDate($result, $today));
    }

    public function testSessionWithoutDurationHasLevelZero(): void
    {
        $today = new \DateTimeImmutable('today');
        $result = $this->build([[
            'performedAt' => $today,
            'duration' => null,
        ]]);

        self::assertSame(0, $this->levelForDate($result, $today));
    }

    public function testMultipleSessionsTheSameDayAreSummed(): void
    {
        $today = new \DateTimeImmutable('today');
        $result = $this->build([
            [
                'performedAt' => $today,
                'duration' => 30,
            ],
            [
                'performedAt' => $today,
                'duration' => 30,
            ],
        ]);

        // 30 + 30 = 60 min, palier "medium" (2), pas "short" (1) comme le donnerait chaque
        // séance isolément.
        self::assertSame(2, $this->levelForDate($result, $today));
    }

    public function testDayCoveredByADeloadPeriodIsFlagged(): void
    {
        $today = new \DateTimeImmutable('today');
        $result = $this->build([], deloadDayDates: [$today]);

        self::assertTrue($this->isDeloadForDate($result, $today));
    }

    /**
     * @param list<array{performedAt: \DateTimeImmutable, duration: int|null}> $rows
     * @param \DateTimeImmutable[] $deloadDayDates
     * @return array{weeks: list<array{days: list<array{date: \DateTimeImmutable, level: int, isDeload: bool}>, isNewMonth: bool}>}
     */
    private function build(array $rows, array $deloadDayDates = []): array
    {
        $workoutRepository = $this->createStub(WorkoutRepository::class);
        $workoutRepository->method('findPerformedAtAndDurationSince')->willReturn($rows);

        $deloadPeriodSetService = $this->createStub(DeloadPeriodSetService::class);
        $deloadPeriodSetService->method('dayKeySet')->willReturn(array_fill_keys(
            array_map(static fn (\DateTimeImmutable $date): string => $date->format('Y-m-d'), $deloadDayDates),
            true,
        ));

        $service = new WorkoutHeatmapService($workoutRepository, new DashboardPeriodCalculator(), $deloadPeriodSetService);

        return $service->build($this->createStub(User::class));
    }

    /**
     * @param array{weeks: list<array{days: list<array{date: \DateTimeImmutable, level: int, isDeload: bool}>, isNewMonth: bool}>} $result
     */
    private function levelForDate(array $result, \DateTimeImmutable $date): int
    {
        return $this->dayForDate($result, $date)['level'];
    }

    /**
     * @param array{weeks: list<array{days: list<array{date: \DateTimeImmutable, level: int, isDeload: bool}>, isNewMonth: bool}>} $result
     */
    private function isDeloadForDate(array $result, \DateTimeImmutable $date): bool
    {
        return $this->dayForDate($result, $date)['isDeload'];
    }

    /**
     * @param array{weeks: list<array{days: list<array{date: \DateTimeImmutable, level: int, isDeload: bool}>, isNewMonth: bool}>} $result
     * @return array{date: \DateTimeImmutable, level: int, isDeload: bool}
     */
    private function dayForDate(array $result, \DateTimeImmutable $date): array
    {
        $dateKey = $date->format('Y-m-d');

        foreach ($result['weeks'] as $week) {
            foreach ($week['days'] as $day) {
                if ($day['date']->format('Y-m-d') === $dateKey) {
                    return $day;
                }
            }
        }

        throw new \LogicException(\sprintf('Date "%s" not found in the heatmap grid.', $dateKey));
    }
}
