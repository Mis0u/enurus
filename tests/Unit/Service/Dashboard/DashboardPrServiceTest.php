<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\User;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\Dashboard\DashboardPrService;
use App\Service\Workout\WorkoutRecordDetectionService;
use PHPUnit\Framework\TestCase;

final class DashboardPrServiceTest extends TestCase
{
    public function testCountPrsByFilterCountsOnlyEventsMatchingTheDayPeriod(): void
    {
        $detectionService = $this->createStub(WorkoutRecordDetectionService::class);
        $detectionService->method('findPrEvents')->willReturn([
            [
                'workoutId' => 'workout-1',
                'performedAt' => new \DateTimeImmutable('-2 days'),
            ],
            [
                'workoutId' => 'workout-2',
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new DashboardPrService($detectionService);
        $week = new DashboardPeriod(new \DateTimeImmutable('-7 days'), new \DateTimeImmutable('+1 day'));
        $day = new DashboardPeriod(new \DateTimeImmutable('now')->setTime(0, 0, 0), new \DateTimeImmutable('now')->setTime(23, 59, 59));

        $result = $service->countPrsByFilter(
            $this->createStub(User::class),
            $day,
            $week,
            $week,
        );

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

        $service = new DashboardPrService($detectionService);
        $week = new DashboardPeriod(new \DateTimeImmutable('-7 days'), new \DateTimeImmutable('+1 day'));
        $day = new DashboardPeriod(new \DateTimeImmutable('now')->setTime(0, 0, 0), new \DateTimeImmutable('now')->setTime(23, 59, 59));

        $result = $service->countRepsRecordsByFilter(
            $this->createStub(User::class),
            $day,
            $week,
            $week,
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

        $detectionService = $this->createStub(WorkoutRecordDetectionService::class);
        $detectionService->method('findPrEvents')->willReturn([
            [
                'workoutId' => 'workout-start',
                'performedAt' => $week->start,
            ],
            [
                'workoutId' => 'workout-end',
                'performedAt' => $week->end,
            ],
            [
                'workoutId' => 'workout-before',
                'performedAt' => $week->start->modify('-1 second'),
            ],
            [
                'workoutId' => 'workout-after',
                'performedAt' => $week->end->modify('+1 second'),
            ],
            [
                // Événement solidement à l'intérieur de la période : casse la symétrie
                // in-range/out-of-range des 4 événements ci-dessus, pour qu'une mutation par
                // négation logique (qui inverserait les deux groupes) ne produise pas
                // accidentellement le même total.
                'workoutId' => 'workout-middle',
                'performedAt' => $week->start->modify('+3 days'),
            ],
        ]);

        $service = new DashboardPrService($detectionService);
        $farPeriod = $this->farPeriod();

        $result = $service->countPrsByFilter(
            $this->createStub(User::class),
            $farPeriod,
            $week,
            $farPeriod,
        );

        self::assertSame(3, $result['week']);
    }

    public function testMonthBoundariesAreInclusive(): void
    {
        $month = new DashboardPeriod(
            new \DateTimeImmutable('2026-01-01 00:00:00'),
            new \DateTimeImmutable('2026-01-31 00:00:00'),
        );

        $detectionService = $this->createStub(WorkoutRecordDetectionService::class);
        $detectionService->method('findPrEvents')->willReturn([
            [
                'workoutId' => 'workout-start',
                'performedAt' => $month->start,
            ],
            [
                'workoutId' => 'workout-end',
                'performedAt' => $month->end,
            ],
            [
                'workoutId' => 'workout-before',
                'performedAt' => $month->start->modify('-1 second'),
            ],
            [
                'workoutId' => 'workout-after',
                'performedAt' => $month->end->modify('+1 second'),
            ],
            [
                // Casse la symétrie in-range/out-of-range, cf. testWeekBoundariesAreInclusive.
                'workoutId' => 'workout-middle',
                'performedAt' => $month->start->modify('+15 days'),
            ],
        ]);

        $service = new DashboardPrService($detectionService);

        $result = $service->countPrsByFilter(
            $this->createStub(User::class),
            $this->farPeriod(),
            $this->farPeriod(),
            $month,
        );

        self::assertSame(3, $result['month']);
    }

    private function farPeriod(): DashboardPeriod
    {
        return new DashboardPeriod(
            new \DateTimeImmutable('2000-01-01'),
            new \DateTimeImmutable('2000-01-31'),
        );
    }
}
