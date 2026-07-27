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

    public function testPercentagesAreRoundedToTheNearestIntegerNotFlooredOrCeiled(): void
    {
        $workoutMuscleRepository = $this->createStub(WorkoutMuscleRepository::class);
        $workoutMuscleRepository->method('findMuscleGroupSetCountsByWorkoutIds')->willReturn([
            [
                'id' => 'muscle-quadriceps',
                'name' => 'quadriceps',
                'sets' => 9,
                'primarySets' => 6,
                'secondarySets' => 3,
            ],
            [
                'id' => 'muscle-biceps',
                'name' => 'biceps',
                'sets' => 3,
                'primarySets' => 1,
                'secondarySets' => 2,
            ],
            [
                'id' => 'muscle-triceps',
                'name' => 'triceps',
                'sets' => 6,
                'primarySets' => 4,
                'secondarySets' => 2,
            ],
        ]);

        $service = new DashboardMuscleDistributionService($workoutMuscleRepository);
        $result = $service->getBars(['workout-1']);

        // Trié par sets décroissant : quadriceps(9), triceps(6), biceps(3). max = 9.
        $quadriceps = $result['bars'][0];
        $triceps = $result['bars'][1];
        $biceps = $result['bars'][2];

        // round(9/9*100)=100 — sets == max.
        self::assertSame(100, $quadriceps['percentage']);
        // round(6/9*100)=round(66.67)=67 : floor donnerait 66.
        self::assertSame(67, $quadriceps['primaryPercentage']);
        self::assertSame(33, $quadriceps['secondaryPercentage']);

        // round(3/9*100)=round(33.33)=33 : ceil donnerait 34.
        self::assertSame(33, $biceps['percentage']);
        self::assertSame(33, $biceps['primaryPercentage']);
        self::assertSame(67, $biceps['secondaryPercentage']);

        // round(6/9*100)=67 : floor donnerait 66.
        self::assertSame(67, $triceps['percentage']);
        self::assertSame(67, $triceps['primaryPercentage']);
        self::assertSame(33, $triceps['secondaryPercentage']);
    }

    public function testRemainingCountIsZeroWhenTotalDoesNotExceedMaxBars(): void
    {
        $counts = [];
        for ($i = 1; 3 >= $i; $i++) {
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

        self::assertSame(0, $result['remainingCount']);
    }
}
