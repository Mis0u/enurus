<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Workout;

use App\Entity\User;
use App\Repository\ExerciseSetRepository;
use App\Service\Workout\WorkoutRecordDetectionService;
use PHPUnit\Framework\TestCase;

final class WorkoutRecordDetectionServiceTest extends TestCase
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

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findPrEvents($this->createStub(User::class));

        self::assertCount(1, $events);
        self::assertSame('workout-1', $events[0]['workoutId']);
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

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findPrEvents($this->createStub(User::class));

        self::assertCount(2, $events);
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

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findPrEvents($this->createStub(User::class));

        self::assertCount(1, $events);
        self::assertSame('workout-1', $events[0]['workoutId']);
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

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findPrEvents($this->createStub(User::class));

        // 80kg après 100kg n'est jamais un record, et retrouver 100kg ensuite n'en est pas un non
        // plus (déjà atteint) — un seul événement, celui du 100kg initial.
        self::assertCount(1, $events);
        self::assertSame('workout-1', $events[0]['workoutId']);
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

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findRepsRecordEvents($this->createStub(User::class));

        // workout-1 est la première fois à 130kg : ne compte pas (firstAttemptCounts = false).
        // workout-2 bat le record de reps à 130kg (10 > 9) : compte pour 1.
        self::assertCount(1, $events);
        self::assertSame('workout-2', $events[0]['workoutId']);
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

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findRepsRecordEvents($this->createStub(User::class));

        self::assertCount(0, $events);
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

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findRepsRecordEvents($this->createStub(User::class));

        self::assertCount(0, $events);
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

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findRepsRecordEvents($this->createStub(User::class));

        // Même poids (100kg), exercices différents : la clé de suivi doit distinguer les deux, donc
        // le premier essai de deadlift à 100kg ne doit pas être comparé aux reps du squat.
        self::assertCount(0, $events);
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

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findRepsRecordEvents($this->createStub(User::class));

        // Même exercice, poids différent (150kg vs 100kg) : la clé de suivi doit distinguer les
        // deux poids, donc le premier essai à 150kg ne doit pas être comparé aux reps à 100kg.
        self::assertCount(0, $events);
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

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findRepsRecordEvents($this->createStub(User::class));

        self::assertCount(0, $events);
    }

    public function testHasPrByWorkoutIdOnlyFlagsRequestedIdsThatHaveAnEvent(): void
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

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $map = $service->hasPrByWorkoutId($this->createStub(User::class), ['workout-1', 'workout-2', 'workout-3']);

        self::assertSame([
            'workout-1' => true,
            'workout-2' => true,
            'workout-3' => false,
        ], $map);
    }

    public function testHasRepsRecordByWorkoutIdOnlyFlagsRequestedIdsThatHaveAnEvent(): void
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

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $map = $service->hasRepsRecordByWorkoutId($this->createStub(User::class), ['workout-1', 'workout-2']);

        self::assertSame([
            'workout-1' => false,
            'workout-2' => true,
        ], $map);
    }

    public function testADurationRecordForATimeBasedExerciseCountsAsAPr(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxDurationPerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'plank',
                'duration' => 480,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findPrEvents($this->createStub(User::class));

        // Un exercice `TIME` jamais fait avant compte automatiquement comme un premier record,
        // exactement comme un poids jamais soulevé (firstAttemptCounts: true).
        self::assertCount(1, $events);
        self::assertSame('workout-1', $events[0]['workoutId']);
    }

    public function testRunningMaxForDurationIsNeverLoweredByASubsequentSmallerValue(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxDurationPerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'plank',
                'duration' => 480,
                'performedAt' => new \DateTimeImmutable('-2 days'),
            ],
            [
                'workoutId' => 'workout-2',
                'exerciseId' => 'plank',
                'duration' => 300,
                'performedAt' => new \DateTimeImmutable('-1 day'),
            ],
            [
                'workoutId' => 'workout-3',
                'exerciseId' => 'plank',
                'duration' => 480,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findPrEvents($this->createStub(User::class));

        // 5 min après 8 min n'est jamais un record, et retrouver 8 min ensuite n'en est pas un
        // non plus (déjà atteint) — un seul événement, celui des 8 min initiales.
        self::assertCount(1, $events);
        self::assertSame('workout-1', $events[0]['workoutId']);
    }

    public function testWeightAndDurationPrStreamsAreMergedAndTrackedIndependently(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxWeightPerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'squat',
                'weight' => 100.0,
                'performedAt' => new \DateTimeImmutable('-1 day'),
            ],
        ]);
        $exerciseSetRepository->method('findMaxDurationPerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-2',
                'exerciseId' => 'plank',
                'duration' => 480,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findPrEvents($this->createStub(User::class));

        // Deux exercices de types différents (poids vs durée), chacun à son premier essai : les
        // deux flux fusionnés produisent bien 2 événements distincts, sans interférence entre eux.
        self::assertCount(2, $events);
        $workoutIds = array_column($events, 'workoutId');
        self::assertContains('workout-1', $workoutIds);
        self::assertContains('workout-2', $workoutIds);
    }

    public function testHasPrByWorkoutIdIncludesDurationBasedRecords(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxDurationPerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'plank',
                'duration' => 480,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $map = $service->hasPrByWorkoutId($this->createStub(User::class), ['workout-1', 'workout-2']);

        self::assertSame([
            'workout-1' => true,
            'workout-2' => false,
        ], $map);
    }

    public function testADistanceRecordForADistanceBasedExerciseCountsAsAPr(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxDistancePerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'farmer-walk',
                'distance' => 100,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findPrEvents($this->createStub(User::class));

        // Un exercice `DISTANCE` jamais fait avant compte automatiquement comme un premier
        // record, exactement comme un poids jamais soulevé (firstAttemptCounts: true).
        self::assertCount(1, $events);
        self::assertSame('workout-1', $events[0]['workoutId']);
    }

    public function testRunningMaxForDistanceIsNeverLoweredByASubsequentSmallerValue(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxDistancePerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'farmer-walk',
                'distance' => 100,
                'performedAt' => new \DateTimeImmutable('-2 days'),
            ],
            [
                'workoutId' => 'workout-2',
                'exerciseId' => 'farmer-walk',
                'distance' => 50,
                'performedAt' => new \DateTimeImmutable('-1 day'),
            ],
            [
                'workoutId' => 'workout-3',
                'exerciseId' => 'farmer-walk',
                'distance' => 100,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findPrEvents($this->createStub(User::class));

        // 50m après 100m n'est jamais un record, et retrouver 100m ensuite n'en est pas un
        // non plus (déjà atteint) — un seul événement, celui des 100m initiaux.
        self::assertCount(1, $events);
        self::assertSame('workout-1', $events[0]['workoutId']);
    }

    public function testWeightAndDistancePrStreamsAreMergedAndTrackedIndependently(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxWeightPerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'squat',
                'weight' => 100.0,
                'performedAt' => new \DateTimeImmutable('-1 day'),
            ],
        ]);
        $exerciseSetRepository->method('findMaxDistancePerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-2',
                'exerciseId' => 'farmer-walk',
                'distance' => 100,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $events = $service->findPrEvents($this->createStub(User::class));

        // Deux exercices de types différents (poids vs distance), chacun à son premier essai :
        // les deux flux fusionnés produisent bien 2 événements distincts, sans interférence.
        self::assertCount(2, $events);
        $workoutIds = array_column($events, 'workoutId');
        self::assertContains('workout-1', $workoutIds);
        self::assertContains('workout-2', $workoutIds);
    }

    public function testHasPrByWorkoutIdIncludesDistanceBasedRecords(): void
    {
        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findMaxDistancePerWorkoutAndExerciseChronologicallyByUser')->willReturn([
            [
                'workoutId' => 'workout-1',
                'exerciseId' => 'farmer-walk',
                'distance' => 100,
                'performedAt' => new \DateTimeImmutable('now'),
            ],
        ]);

        $service = new WorkoutRecordDetectionService($exerciseSetRepository);
        $map = $service->hasPrByWorkoutId($this->createStub(User::class), ['workout-1', 'workout-2']);

        self::assertSame([
            'workout-1' => true,
            'workout-2' => false,
        ], $map);
    }
}
