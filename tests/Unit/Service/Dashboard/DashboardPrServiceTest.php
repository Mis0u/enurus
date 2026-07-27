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

    public function testDifferentExercisesWithSameWeightTrackRepsRecordsIndependently(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxRepsPerWorkoutExerciseAndWeightChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'squat',
                'weight' => 100.0,
                'reps' => 5,
                'performedAt' => new \DateTimeImmutable('-1 day'),
            ],
            [
                'workoutId' => 'workout-2',
                'exerciseId' => 'deadlift',
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

        // Même poids (100kg), exercices différents : la clé de suivi doit distinguer les deux, donc
        // le premier essai de deadlift à 100kg ne doit pas être comparé aux reps du squat.
        self::assertSame(0, $result['last']);
    }

    public function testSameExerciseDifferentWeightTracksRepsRecordsIndependently(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxRepsPerWorkoutExerciseAndWeightChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'squat',
                'weight' => 100.0,
                'reps' => 3,
                'performedAt' => new \DateTimeImmutable('-1 day'),
            ],
            [
                'workoutId' => 'workout-2',
                'exerciseId' => 'squat',
                'weight' => 150.0,
                // Reps volontairement plus élevé que l'entrée à 100kg : si la clé de suivi
                // fusionnait les deux poids par erreur, ceci serait vu à tort comme un record.
                'reps' => 5,
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

        // Même exercice, poids différent (150kg vs 100kg) : la clé de suivi doit distinguer les
        // deux poids, donc le premier essai à 150kg ne doit pas être comparé aux reps à 100kg.
        self::assertSame(0, $result['last']);
    }

    public function testAmbiguousNumericConcatenationDoesNotCollideRepsRecordKeys(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxRepsPerWorkoutExerciseAndWeightChronologicallyByUser')->willReturn([
            // Sans séparateur explicite, "1" + "23.40" et "12" + "3.40" produisent la même chaîne
            // concaténée ("123.40") — le séparateur '|' entre exerciseId et weightKey empêche cette
            // collision.
            [
                'workoutId' => 'workout-1',
                'exerciseId' => '1',
                'weight' => 23.4,
                'reps' => 5,
                'performedAt' => new \DateTimeImmutable('-1 day'),
            ],
            [
                'workoutId' => 'workout-2',
                'exerciseId' => '12',
                'weight' => 3.4,
                'reps' => 9,
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

    public function testRunningMaxIsNeverLoweredByASubsequentSmallerValue(): void
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
                'weight' => 80.0,
                'performedAt' => new \DateTimeImmutable('-1 day'),
            ],
            [
                'workoutId' => 'workout-3',
                'exerciseId' => 'squat',
                'weight' => 100.0,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new DashboardPrService($exerciseSetRepository);

        $resultAfterDrop = $service->countPrsByFilter(
            $this->createStub(User::class),
            'workout-2',
            $this->farPeriod(),
            $this->farPeriod(),
        );
        $resultAfterMatchingOriginalMax = $service->countPrsByFilter(
            $this->createStub(User::class),
            'workout-3',
            $this->farPeriod(),
            $this->farPeriod(),
        );

        // 80kg après 100kg n'est jamais un record...
        self::assertSame(0, $resultAfterDrop['last']);
        // ...et ne doit pas non plus faire redescendre le record suivi à 80kg : retrouver 100kg
        // ensuite n'est donc pas non plus un nouveau record (déjà atteint).
        self::assertSame(0, $resultAfterMatchingOriginalMax['last']);
    }

    public function testWeekBoundariesAreInclusive(): void
    {
        $week = new DashboardPeriod(
            new \DateTimeImmutable('2026-01-08 00:00:00'),
            new \DateTimeImmutable('2026-01-14 00:00:00'),
        );

        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxWeightPerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-start',
                'exerciseId' => 'e1',
                'weight' => 10.0,
                'performedAt' => $week->start,
            ],
            [
                'workoutId' => 'workout-end',
                'exerciseId' => 'e2',
                'weight' => 20.0,
                'performedAt' => $week->end,
            ],
            [
                'workoutId' => 'workout-before',
                'exerciseId' => 'e3',
                'weight' => 30.0,
                'performedAt' => $week->start->modify('-1 second'),
            ],
            [
                'workoutId' => 'workout-after',
                'exerciseId' => 'e4',
                'weight' => 40.0,
                'performedAt' => $week->end->modify('+1 second'),
            ],
            [
                // Événement solidement à l'intérieur de la période : casse la symétrie
                // in-range/out-of-range des 4 événements ci-dessus, pour qu'une mutation par
                // négation logique (qui inverserait les deux groupes) ne produise pas
                // accidentellement le même total.
                'workoutId' => 'workout-middle',
                'exerciseId' => 'e5',
                'weight' => 50.0,
                'performedAt' => $week->start->modify('+3 days'),
            ],
        ]);

        $service = new DashboardPrService($exerciseSetRepository);

        $result = $service->countPrsByFilter(
            $this->createStub(User::class),
            'workout-none',
            $week,
            $this->farPeriod(),
        );

        self::assertSame(3, $result['week']);
    }

    public function testMonthBoundariesAreInclusive(): void
    {
        $month = new DashboardPeriod(
            new \DateTimeImmutable('2026-01-01 00:00:00'),
            new \DateTimeImmutable('2026-01-31 00:00:00'),
        );

        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxWeightPerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-start',
                'exerciseId' => 'e1',
                'weight' => 10.0,
                'performedAt' => $month->start,
            ],
            [
                'workoutId' => 'workout-end',
                'exerciseId' => 'e2',
                'weight' => 20.0,
                'performedAt' => $month->end,
            ],
            [
                'workoutId' => 'workout-before',
                'exerciseId' => 'e3',
                'weight' => 30.0,
                'performedAt' => $month->start->modify('-1 second'),
            ],
            [
                'workoutId' => 'workout-after',
                'exerciseId' => 'e4',
                'weight' => 40.0,
                'performedAt' => $month->end->modify('+1 second'),
            ],
            [
                // Casse la symétrie in-range/out-of-range, cf. testWeekBoundariesAreInclusive.
                'workoutId' => 'workout-middle',
                'exerciseId' => 'e5',
                'weight' => 50.0,
                'performedAt' => $month->start->modify('+15 days'),
            ],
        ]);

        $service = new DashboardPrService($exerciseSetRepository);

        $result = $service->countPrsByFilter(
            $this->createStub(User::class),
            'workout-none',
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
