<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\User;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\Dashboard\DashboardPeriods;
use App\Service\Dashboard\DashboardPrService;
use App\Service\Workout\WorkoutRecordDetectionService;
use PHPUnit\Framework\TestCase;

final class DashboardPrServiceTest extends TestCase
{
    public function testCountPrsByFilterCountsOnlyEventsMatchingTheDayPeriod(): void
    {
        $week = new DashboardPeriod(new \DateTimeImmutable('-7 days'), new \DateTimeImmutable('+1 day'));
        $day = new DashboardPeriod(new \DateTimeImmutable('now')->setTime(0, 0, 0), new \DateTimeImmutable('now')->setTime(23, 59, 59));

        $result = $this->createService()->countPrsByFilter([
            [
                'workoutId' => 'workout-1',
                'performedAt' => new \DateTimeImmutable('-2 days'),
            ],
            [
                'workoutId' => 'workout-2',
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ], $this->periods($day, $week, $week));

        self::assertSame(2, $result['week']);
        self::assertSame(1, $result['last']);
    }

    public function testCountRepsRecordsByFilterCountsOnlyEventsMatchingTheDayPeriod(): void
    {
        $detectionService = $this->createStub(WorkoutRecordDetectionService::class);
        $detectionService->method('findRepsRecordEvents')->willReturn([
            [
                'workoutId' => 'workout-2',
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $week = new DashboardPeriod(new \DateTimeImmutable('-7 days'), new \DateTimeImmutable('+1 day'));
        $day = new DashboardPeriod(new \DateTimeImmutable('now')->setTime(0, 0, 0), new \DateTimeImmutable('now')->setTime(23, 59, 59));

        $result = new DashboardPrService($detectionService)->countRepsRecordsByFilter(
            $this->createStub(User::class),
            $this->periods($day, $week, $week),
        );

        self::assertSame(1, $result['last']);
        self::assertSame(1, $result['week']);
    }

    public function testWeekBoundariesAreInclusive(): void
    {
        $week = new DashboardPeriod(
            new \DateTimeImmutable('2026-01-08 00:00:00'),
            new \DateTimeImmutable('2026-01-14 00:00:00'),
        );

        $result = $this->createService()->countPrsByFilter(
            // Événement solidement à l'intérieur de la période : casse la symétrie
            // in-range/out-of-range des 4 bornes, pour qu'une mutation par négation logique (qui
            // inverserait les deux groupes) ne produise pas accidentellement le même total.
            $this->boundaryEvents($week, $week->start->modify('+3 days')),
            $this->periods($this->farPeriod(), $week, $this->farPeriod()),
        );

        self::assertSame(3, $result['week']);
    }

    public function testMonthBoundariesAreInclusive(): void
    {
        $month = new DashboardPeriod(
            new \DateTimeImmutable('2026-01-01 00:00:00'),
            new \DateTimeImmutable('2026-01-31 00:00:00'),
        );

        $result = $this->createService()->countPrsByFilter(
            // Casse la symétrie in-range/out-of-range, cf. testWeekBoundariesAreInclusive.
            $this->boundaryEvents($month, $month->start->modify('+15 days')),
            $this->periods($this->farPeriod(), $this->farPeriod(), $month),
        );

        self::assertSame(3, $result['month']);
    }

    private function createService(): DashboardPrService
    {
        return new DashboardPrService($this->createStub(WorkoutRecordDetectionService::class));
    }

    /**
     * Un événement sur chaque borne, un juste avant, un juste après, et un au milieu.
     *
     * @return array<int, array{workoutId: string, performedAt: \DateTimeImmutable}>
     */
    private function boundaryEvents(DashboardPeriod $period, \DateTimeImmutable $middle): array
    {
        return [
            [
                'workoutId' => 'workout-start',
                'performedAt' => $period->start,
            ],
            [
                'workoutId' => 'workout-end',
                'performedAt' => $period->end,
            ],
            [
                'workoutId' => 'workout-before',
                'performedAt' => $period->start->modify('-1 second'),
            ],
            [
                'workoutId' => 'workout-after',
                'performedAt' => $period->end->modify('+1 second'),
            ],
            [
                'workoutId' => 'workout-middle',
                'performedAt' => $middle,
            ],
        ];
    }

    private function periods(DashboardPeriod $day, DashboardPeriod $week, DashboardPeriod $month): DashboardPeriods
    {
        return new DashboardPeriods($day, $week, $month, $this->farPeriod());
    }

    private function farPeriod(): DashboardPeriod
    {
        return new DashboardPeriod(
            new \DateTimeImmutable('2000-01-01'),
            new \DateTimeImmutable('2000-01-31'),
        );
    }
}
