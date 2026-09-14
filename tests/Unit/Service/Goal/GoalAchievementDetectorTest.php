<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Goal;

use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\ExerciseGoalRepository;
use App\Repository\ExerciseSetRepository;
use App\Service\Goal\GoalAchievementDetector;
use App\Service\Goal\GoalProgressResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class GoalAchievementDetectorTest extends TestCase
{
    public function testNewlyAchievedGoalIsDetected(): void
    {
        $exercise = $this->exerciseWithId();
        $goal = $this->weightGoal($exercise, target: 100.0);
        $workout = $this->workoutFor($exercise);
        $workoutId = (string) $workout->id;

        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findSessionHistoryForExerciseAndUser')->willReturn([
            $this->row('other-workout', 80.0),
            $this->row($workoutId, 110.0),
        ]);

        $exerciseGoalRepository = $this->createStub(ExerciseGoalRepository::class);
        $exerciseGoalRepository->method('findAllByOwnerAndExercise')->willReturn([$goal]);

        $detector = new GoalAchievementDetector($exerciseGoalRepository, new GoalProgressResolver($exerciseSetRepository));

        $result = $detector->detectNewlyAchieved($this->createStub(User::class), $workout);

        self::assertSame([$goal], $result);
    }

    public function testAlreadyAchievedGoalIsNotReportedAgain(): void
    {
        $exercise = $this->exerciseWithId();
        $goal = $this->weightGoal($exercise, target: 100.0);
        $workout = $this->workoutFor($exercise);
        $workoutId = (string) $workout->id;

        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findSessionHistoryForExerciseAndUser')->willReturn([
            $this->row('other-workout', 150.0),
            $this->row($workoutId, 110.0),
        ]);

        $exerciseGoalRepository = $this->createStub(ExerciseGoalRepository::class);
        $exerciseGoalRepository->method('findAllByOwnerAndExercise')->willReturn([$goal]);

        $detector = new GoalAchievementDetector($exerciseGoalRepository, new GoalProgressResolver($exerciseSetRepository));

        $result = $detector->detectNewlyAchieved($this->createStub(User::class), $workout);

        self::assertSame([], $result);
    }

    public function testAlreadyAchievedHistoryGoalIsIgnoredWhileAnotherIsStillNewlyAchieved(): void
    {
        $exercise = $this->exerciseWithId();
        $historyGoal = $this->weightGoal($exercise, target: 80.0);
        $activeGoal = $this->weightGoal($exercise, target: 100.0);
        $workout = $this->workoutFor($exercise);
        $workoutId = (string) $workout->id;

        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findSessionHistoryForExerciseAndUser')->willReturn([
            $this->row('other-workout', 85.0),
            $this->row($workoutId, 110.0),
        ]);

        $exerciseGoalRepository = $this->createStub(ExerciseGoalRepository::class);
        $exerciseGoalRepository->method('findAllByOwnerAndExercise')->willReturn([$historyGoal, $activeGoal]);

        $detector = new GoalAchievementDetector($exerciseGoalRepository, new GoalProgressResolver($exerciseSetRepository));

        $result = $detector->detectNewlyAchieved($this->createStub(User::class), $workout);

        self::assertSame([$activeGoal], $result);
    }

    public function testNoGoalMeansNothingDetected(): void
    {
        $exercise = $this->exerciseWithId();
        $workout = $this->workoutFor($exercise);

        $exerciseGoalRepository = $this->createStub(ExerciseGoalRepository::class);
        $exerciseGoalRepository->method('findAllByOwnerAndExercise')->willReturn([]);

        $detector = new GoalAchievementDetector($exerciseGoalRepository, new GoalProgressResolver($this->createStub(ExerciseSetRepository::class)));

        $result = $detector->detectNewlyAchieved($this->createStub(User::class), $workout);

        self::assertSame([], $result);
    }

    private function exerciseWithId(): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Squat';
        $exercise->measurementType = MeasurementType::WEIGHT_REPS;
        (new \ReflectionProperty(Exercise::class, 'id'))->setValue($exercise, Uuid::v4());

        return $exercise;
    }

    private function weightGoal(Exercise $exercise, float $target): ExerciseGoal
    {
        $goal = new ExerciseGoal();
        $goal->owner = $this->createStub(User::class);
        $goal->exercise = $exercise;
        $goal->measurementType = MeasurementType::WEIGHT_REPS;
        $goal->targetWeight = $target;

        return $goal;
    }

    private function workoutFor(Exercise $exercise): Workout
    {
        $workout = new Workout();
        (new \ReflectionProperty(Workout::class, 'id'))->setValue($workout, Uuid::v4());

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->workout = $workout;
        $workoutExercise->exercise = $exercise;
        $workout->addWorkoutExercise($workoutExercise);

        return $workout;
    }

    /**
     * @return array{workoutId: string, performedAt: \DateTimeImmutable, weight: float, reps: int, duration: ?int, distance: ?int}
     */
    private function row(string $workoutId, float $weight): array
    {
        return [
            'workoutId' => $workoutId,
            'performedAt' => new \DateTimeImmutable('now'),
            'weight' => $weight,
            'reps' => 5,
            'duration' => null,
            'distance' => null,
        ];
    }
}
