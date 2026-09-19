<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\User;
use App\Repository\WorkoutMuscleRepository;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardMuscleDistributionService;
use App\Service\Dashboard\DashboardMuscleViewBuilder;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\Dashboard\DashboardPeriods;
use PHPUnit\Framework\TestCase;

final class DashboardMuscleViewBuilderTest extends TestCase
{
    private const array EMPTY_BARS = [
        'bars' => [],
        'remainingCount' => 0,
    ];

    public function testSessionFilterIsBuiltFromTheGivenLastTrainingDayWorkouts(): void
    {
        $view = $this->createBuilder()->build(new User(), $this->createPeriods(), ['day-1'], false);

        self::assertSame(['day-1'], $view->session->primary);
        self::assertSame(['day-1-secondary'], $view->session->secondary);
        self::assertSame(self::EMPTY_BARS, $view->session->bars);
    }

    public function testWeekAndMonthStayEmptyAndCostNothingWhileLocked(): void
    {
        $statsRepository = $this->createMock(WorkoutStatsRepository::class);
        $statsRepository->expects(self::never())->method('findIdsByUserAndDateRange');

        $muscleRepository = $this->createMock(WorkoutMuscleRepository::class);
        $muscleRepository->expects(self::never())->method('findLastSolicitationDatesByMuscleGroup');
        $muscleRepository->method('findSvgIdsByWorkoutIds')->willReturn([
            'primary' => [],
            'secondary' => [],
        ]);

        $view = $this->createBuilder($statsRepository, $muscleRepository)->build(new User(), $this->createPeriods(), ['day-1'], false);

        foreach ([$view->week, $view->month] as $filter) {
            self::assertSame([], $filter->primary);
            self::assertSame([], $filter->secondary);
            self::assertSame(self::EMPTY_BARS, $filter->bars);
        }
    }

    public function testWeekAndMonthAreBuiltFromTheWorkoutsOfTheirOwnPeriodOnceUnlocked(): void
    {
        $view = $this->createBuilder()->build(new User(), $this->createPeriods(), ['day-1'], true);

        self::assertSame(['week-1'], $view->week->primary);
        self::assertSame(['week-1-secondary'], $view->week->secondary);
        self::assertSame(['month-1', 'month-2'], $view->month->primary);
        self::assertSame(['month-1-secondary', 'month-2-secondary'], $view->month->secondary);
    }

    public function testLastSolicitationDatesAreFetchedOnceForBothWeekAndMonth(): void
    {
        $muscleRepository = $this->createMock(WorkoutMuscleRepository::class);
        $muscleRepository->expects(self::once())->method('findLastSolicitationDatesByMuscleGroup')->willReturn([]);
        $muscleRepository->method('findSvgIdsByWorkoutIds')->willReturn([
            'primary' => [],
            'secondary' => [],
        ]);
        $muscleRepository->method('findMuscleGroupSetCountsByWorkoutIds')->willReturn([]);

        $this->createBuilder(null, $muscleRepository)->build(new User(), $this->createPeriods(), ['day-1'], true);
    }

    private function createPeriods(): DashboardPeriods
    {
        return new DashboardPeriods(
            day: new DashboardPeriod(new \DateTimeImmutable('2026-09-10 00:00:00'), new \DateTimeImmutable('2026-09-10 23:59:59')),
            week: new DashboardPeriod(new \DateTimeImmutable('2026-09-07 00:00:00'), new \DateTimeImmutable('2026-09-13 23:59:59')),
            month: new DashboardPeriod(new \DateTimeImmutable('2026-09-01 00:00:00'), new \DateTimeImmutable('2026-09-18 23:59:59')),
            year: new DashboardPeriod(new \DateTimeImmutable('2026-01-01 00:00:00'), new \DateTimeImmutable('2026-09-18 23:59:59')),
        );
    }

    private function createBuilder(
        ?WorkoutStatsRepository $statsRepository = null,
        ?WorkoutMuscleRepository $muscleRepository = null,
    ): DashboardMuscleViewBuilder {
        $statsRepository ??= $this->createStatsRepositoryStub();
        $muscleRepository ??= $this->createMuscleRepositoryStub();

        return new DashboardMuscleViewBuilder(
            $statsRepository,
            $muscleRepository,
            new DashboardMuscleDistributionService($muscleRepository),
        );
    }

    private function createStatsRepositoryStub(): WorkoutStatsRepository
    {
        $repository = $this->createStub(WorkoutStatsRepository::class);
        $repository->method('findIdsByUserAndDateRange')->willReturnCallback(
            static fn (User $user, \DateTimeImmutable $start): array => '09-07' === $start->format('m-d')
                ? ['week-1']
                : ['month-1', 'month-2'],
        );

        return $repository;
    }

    private function createMuscleRepositoryStub(): WorkoutMuscleRepository
    {
        $repository = $this->createStub(WorkoutMuscleRepository::class);
        $repository->method('findSvgIdsByWorkoutIds')->willReturnCallback(
            static fn (array $workoutIds): array => [
                'primary' => $workoutIds,
                'secondary' => array_map(static fn (string $id): string => $id . '-secondary', $workoutIds),
            ],
        );
        $repository->method('findLastSolicitationDatesByMuscleGroup')->willReturn([]);
        $repository->method('findMuscleGroupSetCountsByWorkoutIds')->willReturn([]);

        return $repository;
    }
}
