<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Goal;

use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Entity\User;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\ExerciseSetRepository;
use App\Service\Goal\GoalProgressResolver;
use PHPUnit\Framework\TestCase;

final class GoalProgressResolverTest extends TestCase
{
    public function testResolveUsesAllSessions(): void
    {
        $goal = $this->weightGoal(target: 100.0);
        $repository = $this->createStub(ExerciseSetRepository::class);
        $repository->method('findSessionHistoryForExerciseAndUser')->willReturn([
            $this->row('workout-a', 90.0),
            $this->row('workout-b', 110.0),
        ]);

        $progress = (new GoalProgressResolver($repository))->resolve($this->createStub(User::class), $goal);

        self::assertTrue($progress->achieved);
    }

    /**
     * Sert à `GoalAchievementDetector` : sans la séance qui portait le record, l'objectif ne doit
     * plus apparaître comme atteint.
     */
    public function testResolveExcludingWorkoutIgnoresThatSession(): void
    {
        $goal = $this->weightGoal(target: 100.0);
        $repository = $this->createStub(ExerciseSetRepository::class);
        $repository->method('findSessionHistoryForExerciseAndUser')->willReturn([
            $this->row('workout-a', 90.0),
            $this->row('workout-b', 110.0),
        ]);

        $progress = (new GoalProgressResolver($repository))->resolveExcludingWorkout(
            $this->createStub(User::class),
            $goal,
            'workout-b',
        );

        self::assertFalse($progress->achieved);
        self::assertSame(90.0, $progress->currentValue);
    }

    private function weightGoal(float $target): ExerciseGoal
    {
        $goal = new ExerciseGoal();
        $goal->owner = $this->createStub(User::class);
        $goal->exercise = new Exercise();
        $goal->measurementType = MeasurementType::WEIGHT_REPS;
        $goal->targetWeight = $target;

        return $goal;
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
