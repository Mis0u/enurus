<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Routine;

use App\Entity\Exercise;
use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Entity\User;
use App\Repository\RoutineMuscleRepository;
use App\Repository\RoutineStatsRepository;
use App\Service\Dashboard\DashboardPeriodCalculator;
use App\Service\Routine\RoutineShowDataService;
use PHPUnit\Framework\TestCase;

final class RoutineShowDataServiceTest extends TestCase
{
    public function testBuildOrdersExercisesByPosition(): void
    {
        $routine = $this->createRoutineWithExercises();

        $service = $this->createService();
        $data = $service->build($routine, $this->createUser());

        self::assertSame(
            ['second', 'first'],
            array_map(static fn (RoutineExercise $re): string => $re->exercise->name, $data['routineExercises']),
        );
    }

    public function testBuildReturnsEmptyUsageStateWhenRoutineNeverUsed(): void
    {
        $routine = $this->createRoutineWithExercises();

        $service = $this->createService(usageDates: []);
        $data = $service->build($routine, $this->createUser());

        self::assertFalse($data['hasUsage']);
        self::assertSame(0, $data['usageCount']);
        self::assertNull($data['lastUsedAt']);
        self::assertSame(0, $data['weekCount']);
        self::assertSame(0, $data['monthCount']);
        self::assertSame(0, $data['yearCount']);
    }

    public function testBuildReturnsUsageStatsWhenRoutineWasUsed(): void
    {
        $routine = $this->createRoutineWithExercises();
        $usageDates = [new \DateTimeImmutable('2026-01-05'), new \DateTimeImmutable('2026-02-10')];

        $service = $this->createService(usageDates: $usageDates, averageDuration: 52.5);
        $data = $service->build($routine, $this->createUser());

        self::assertTrue($data['hasUsage']);
        self::assertSame(2, $data['usageCount']);
        self::assertEquals($usageDates[1], $data['lastUsedAt']);
        self::assertSame(53, $data['averageDurationMinutes']);
    }

    public function testBuildCountsUsagesWithinCurrentWeekMonthAndYear(): void
    {
        $routine = $this->createRoutineWithExercises();
        $now = new \DateTimeImmutable();
        $usageDates = [$now->modify('-2 years'), $now];

        $service = $this->createService(usageDates: $usageDates);
        $data = $service->build($routine, $this->createUser());

        self::assertSame(1, $data['weekCount']);
        self::assertSame(1, $data['monthCount']);
        self::assertSame(1, $data['yearCount']);
    }

    public function testBuildReturnsNullAverageDurationWhenNoSessionHasADurationRecorded(): void
    {
        $routine = $this->createRoutineWithExercises();

        $service = $this->createService(usageDates: [new \DateTimeImmutable('2026-01-05')], averageDuration: null);
        $data = $service->build($routine, $this->createUser());

        self::assertNull($data['averageDurationMinutes']);
    }

    /**
     * @param list<\DateTimeImmutable> $usageDates
     */
    private function createService(array $usageDates = [], ?float $averageDuration = null): RoutineShowDataService
    {
        $muscleRepository = $this->createStub(RoutineMuscleRepository::class);
        $muscleRepository->method('findSvgIdsByRoutine')->willReturn([
            'primary' => [],
            'secondary' => [],
        ]);

        $statsRepository = $this->createStub(RoutineStatsRepository::class);
        $statsRepository->method('findUsageDatesByRoutine')->willReturn($usageDates);
        $statsRepository->method('lastUsedAtFromDates')->willReturn([] === $usageDates ? null : end($usageDates));
        $statsRepository->method('averageDurationByRoutine')->willReturn($averageDuration);

        return new RoutineShowDataService(
            $muscleRepository,
            $statsRepository,
            new DashboardPeriodCalculator(),
        );
    }

    private function createRoutineWithExercises(): Routine
    {
        $owner = $this->createUser();

        $routine = new Routine();
        $routine->owner = $owner;
        $routine->name = 'Push Day';

        $first = new RoutineExercise();
        $first->routine = $routine;
        $first->exercise = $this->createExercise('first');
        $first->position = 2;

        $second = new RoutineExercise();
        $second->routine = $routine;
        $second->exercise = $this->createExercise('second');
        $second->position = 1;

        $routine->routineExercises->add($first);
        $routine->routineExercises->add($second);

        return $routine;
    }

    private function createExercise(string $name): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = $name;
        $exercise->isPublic = true;

        return $exercise;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->email = 'owner@test.com';
        $user->password = 'hashed';
        $user->nickname = 'User';
        $user->lastLogin = new \DateTimeImmutable();
        $user->locale = 'fr';

        return $user;
    }
}
