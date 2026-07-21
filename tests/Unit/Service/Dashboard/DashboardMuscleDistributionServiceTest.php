<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Repository\WorkoutMuscleRepository;
use App\Service\Dashboard\DashboardMuscleDistributionService;
use PHPUnit\Framework\TestCase;

final class DashboardMuscleDistributionServiceTest extends TestCase
{
    public function testBarsAreSortedByTotalSetsRegardlessOfPrimaryInvolvement(): void
    {
        $workoutMuscleRepository = $this->createStub(WorkoutMuscleRepository::class);
        $workoutMuscleRepository->method('findMuscleGroupSetCountsByWorkoutIds')->willReturn([
            [
                'id' => 'muscle-biceps',
                'name' => 'biceps',
                'sets' => 1,
                'primarySets' => 1,
                'secondarySets' => 0,
            ],
            [
                'id' => '2',
                'name' => 'quadriceps',
                'sets' => 5,
                'primarySets' => 0,
                'secondarySets' => 5,
            ],
        ]);

        $service = new DashboardMuscleDistributionService($workoutMuscleRepository);
        $result = $service->getBars(['workout-1']);

        self::assertSame('quadriceps', $result['bars'][0]['name']);
        self::assertSame('biceps', $result['bars'][1]['name']);
    }

    public function testTop8LimitAndRemainingCount(): void
    {
        $counts = [];
        for ($i = 1; 10 >= $i; $i++) {
            $counts[] = [
                'id' => "muscle-{$i}",
                'name' => "muscle-{$i}",
                'sets' => $i,
                'primarySets' => $i,
                'secondarySets' => 0,
            ];
        }

        $workoutMuscleRepository = $this->createStub(WorkoutMuscleRepository::class);
        $workoutMuscleRepository->method('findMuscleGroupSetCountsByWorkoutIds')->willReturn($counts);

        $service = new DashboardMuscleDistributionService($workoutMuscleRepository);
        $result = $service->getBars(['workout-1']);

        self::assertCount(8, $result['bars']);
        self::assertSame(2, $result['remainingCount']);
        self::assertSame('muscle-10', $result['bars'][0]['name']);
        self::assertSame('muscle-3', $result['bars'][7]['name']);
    }

    public function testDaysSinceLastSolicitedIsNullWhenNoMapProvided(): void
    {
        $workoutMuscleRepository = $this->createStub(WorkoutMuscleRepository::class);
        $workoutMuscleRepository->method('findMuscleGroupSetCountsByWorkoutIds')->willReturn([
            [
                'id' => 'muscle-biceps',
                'name' => 'biceps',
                'sets' => 3,
                'primarySets' => 3,
                'secondarySets' => 0,
            ],
        ]);

        $service = new DashboardMuscleDistributionService($workoutMuscleRepository);
        $result = $service->getBars(['workout-1']);

        self::assertNull($result['bars'][0]['daysSinceLastSolicited']);
    }

    public function testDaysSinceLastSolicitedIsZeroWhenSolicitedToday(): void
    {
        $workoutMuscleRepository = $this->createStub(WorkoutMuscleRepository::class);
        $workoutMuscleRepository->method('findMuscleGroupSetCountsByWorkoutIds')->willReturn([
            [
                'id' => 'muscle-biceps',
                'name' => 'biceps',
                'sets' => 3,
                'primarySets' => 3,
                'secondarySets' => 0,
            ],
        ]);

        $service = new DashboardMuscleDistributionService($workoutMuscleRepository);
        $result = $service->getBars(['workout-1'], [
            'muscle-biceps' => new \DateTimeImmutable('now'),
        ]);

        self::assertSame(0, $result['bars'][0]['daysSinceLastSolicited']);
    }

    public function testDaysSinceLastSolicitedIsComputedInCalendarDaysForPastDate(): void
    {
        $workoutMuscleRepository = $this->createStub(WorkoutMuscleRepository::class);
        $workoutMuscleRepository->method('findMuscleGroupSetCountsByWorkoutIds')->willReturn([
            [
                'id' => 'muscle-biceps',
                'name' => 'biceps',
                'sets' => 3,
                'primarySets' => 3,
                'secondarySets' => 0,
            ],
        ]);

        $service = new DashboardMuscleDistributionService($workoutMuscleRepository);
        $result = $service->getBars(['workout-1'], [
            'muscle-biceps' => new \DateTimeImmutable('-3 days'),
        ]);

        self::assertSame(3, $result['bars'][0]['daysSinceLastSolicited']);
    }

    public function testDaysSinceLastSolicitedIsNullWhenMuscleGroupIdIsMissingFromMap(): void
    {
        $workoutMuscleRepository = $this->createStub(WorkoutMuscleRepository::class);
        $workoutMuscleRepository->method('findMuscleGroupSetCountsByWorkoutIds')->willReturn([
            [
                'id' => 'muscle-biceps',
                'name' => 'biceps',
                'sets' => 3,
                'primarySets' => 3,
                'secondarySets' => 0,
            ],
        ]);

        $service = new DashboardMuscleDistributionService($workoutMuscleRepository);
        $result = $service->getBars(['workout-1'], [
            'other-id' => new \DateTimeImmutable('-3 days'),
        ]);

        self::assertNull($result['bars'][0]['daysSinceLastSolicited']);
    }
}
