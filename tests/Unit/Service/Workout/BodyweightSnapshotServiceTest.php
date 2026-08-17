<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Workout;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Service\Workout\BodyweightSnapshotService;
use PHPUnit\Framework\TestCase;

final class BodyweightSnapshotServiceTest extends TestCase
{
    public function testSnapshotsUserBodyweightOnANewSetOfABodyweightExercise(): void
    {
        $user = new User();
        $user->bodyweightKg = 70.0;

        $exercise = new Exercise();
        $exercise->bodyweightPercent = 70.0;

        $set = new ExerciseSet();
        $set->reps = 12;

        $workout = $this->buildWorkout($exercise, $set);

        (new BodyweightSnapshotService())->apply($workout, $user);

        self::assertSame(70.0, $set->bodyweightSnapshotKg);
    }

    public function testNeverOverwritesAnAlreadySnapshottedSet(): void
    {
        $user = new User();
        $user->bodyweightKg = 80.0;

        $exercise = new Exercise();
        $exercise->bodyweightPercent = 70.0;

        $set = new ExerciseSet();
        $set->reps = 12;
        $set->bodyweightSnapshotKg = 65.0;

        $workout = $this->buildWorkout($exercise, $set);

        (new BodyweightSnapshotService())->apply($workout, $user);

        self::assertSame(65.0, $set->bodyweightSnapshotKg);
    }

    public function testLeavesNonBodyweightExercisesUntouched(): void
    {
        $user = new User();
        $user->bodyweightKg = 70.0;

        $exercise = new Exercise();
        $set = new ExerciseSet();
        $set->weight = 100.0;
        $set->reps = 5;

        $workout = $this->buildWorkout($exercise, $set);

        (new BodyweightSnapshotService())->apply($workout, $user);

        self::assertNull($set->bodyweightSnapshotKg);
    }

    private function buildWorkout(Exercise $exercise, ExerciseSet $set): Workout
    {
        $workout = new Workout();

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = 0;
        $workout->addWorkoutExercise($workoutExercise);

        $set->position = 0;
        $workoutExercise->addExerciseSet($set);

        return $workout;
    }
}
