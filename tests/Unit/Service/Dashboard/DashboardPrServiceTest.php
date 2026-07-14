<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\User;
use App\Repository\ExerciseSetRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\Dashboard\DashboardPrService;
use PHPUnit\Framework\TestCase;

final class DashboardPrServiceTest extends TestCase
{
    public function testASingleWorkoutWithProgressiveSetsOnTheSameExerciseCountsOnlyOnePr(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxWeightPerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'skull-crusher',
                // Le repository agrège déjà en max par (séance, exercice) — même une séance avec
                // 3 sets progressifs (100/110/130) ne remonte ici qu'une seule ligne à 130.
                'weight' => 130.0,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new DashboardPrService($exerciseSetRepository);
        $result = $service->countPrsByFilter(
            $this->createStub(User::class),
            'workout-1',
            $this->farPeriod(),
            $this->farPeriod(),
        );

        self::assertSame(1, $result['last']);
    }

    public function testTwoDistinctWorkoutsEachBreakingTheRecordCountAsTwoPrs(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxWeightPerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'squat',
                'weight' => 100.0,
                'performedAt' => new \DateTimeImmutable('-2 days'),
            ],
            [
                'workoutId' => 'workout-2',
                'exerciseId' => 'squat',
                'weight' => 110.0,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new DashboardPrService($exerciseSetRepository);
        $week = new DashboardPeriod(
            new \DateTimeImmutable('-7 days'),
            new \DateTimeImmutable('+1 day'),
        );

        $result = $service->countPrsByFilter(
            $this->createStub(User::class),
            'workout-2',
            $week,
            $week,
        );

        self::assertSame(2, $result['week']);
        self::assertSame(1, $result['last']);
    }

    public function testAWorkoutMatchingThePreviousRecordExactlyIsNotANewPr(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxWeightPerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'bench-press',
                'weight' => 100.0,
                'performedAt' => new \DateTimeImmutable('-1 day'),
            ],
            [
                'workoutId' => 'workout-2',
                'exerciseId' => 'bench-press',
                'weight' => 100.0,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new DashboardPrService($exerciseSetRepository);

        $result = $service->countPrsByFilter(
            $this->createStub(User::class),
            'workout-2',
            $this->farPeriod(),
            $this->farPeriod(),
        );

        self::assertSame(0, $result['last']);
    }

    public function testSameWeightWithMoreRepsCountsAsARepsRecord(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxRepsPerWorkoutExerciseAndWeightChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'skull-crusher',
                'weight' => 130.0,
                'reps' => 9,
                'performedAt' => new \DateTimeImmutable('-2 days'),
            ],
            [
                'workoutId' => 'workout-2',
                'exerciseId' => 'skull-crusher',
                'weight' => 130.0,
                'reps' => 10,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new DashboardPrService($exerciseSetRepository);
        $week = new DashboardPeriod(new \DateTimeImmutable('-7 days'), new \DateTimeImmutable('+1 day'));

        $result = $service->countRepsRecordsByFilter(
            $this->createStub(User::class),
            'workout-2',
            $week,
            $week,
        );

        // workout-1 est la première fois à 130kg : ne compte pas (firstAttemptCounts = false).
        // workout-2 bat le record de reps à 130kg (10 > 9) : compte pour 1.
        self::assertSame(1, $result['last']);
        self::assertSame(1, $result['week']);
    }

    public function testANeverAttemptedWeightNeverCountsAsARepsRecord(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxRepsPerWorkoutExerciseAndWeightChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'squat',
                'weight' => 100.0,
                'reps' => 5,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new DashboardPrService($exerciseSetRepository);

        $result = $service->countRepsRecordsByFilter(
            $this->createStub(User::class),
            'workout-1',
            $this->farPeriod(),
            $this->farPeriod(),
        );

        self::assertSame(0, $result['last']);
    }

    public function testMatchingThePreviousRepsExactlyIsNotANewRepsRecord(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxRepsPerWorkoutExerciseAndWeightChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'bench-press',
                'weight' => 100.0,
                'reps' => 8,
                'performedAt' => new \DateTimeImmutable('-1 day'),
            ],
            [
                'workoutId' => 'workout-2',
                'exerciseId' => 'bench-press',
                'weight' => 100.0,
                'reps' => 8,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new DashboardPrService($exerciseSetRepository);

        $result = $service->countRepsRecordsByFilter(
            $this->createStub(User::class),
            'workout-2',
            $this->farPeriod(),
            $this->farPeriod(),
        );

        self::assertSame(0, $result['last']);
    }

    private function farPeriod(): DashboardPeriod
    {
        return new DashboardPeriod(
            new \DateTimeImmutable('2000-01-01'),
            new \DateTimeImmutable('2000-01-31'),
        );
    }
}
