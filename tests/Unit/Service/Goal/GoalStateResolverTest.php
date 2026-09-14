<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Goal;

use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Entity\User;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\ExerciseSetRepository;
use App\Service\Goal\GoalProgressResolver;
use App\Service\Goal\GoalStateResolver;
use PHPUnit\Framework\TestCase;

final class GoalStateResolverTest extends TestCase
{
    public function testFindActiveGoalIgnoresAlreadyAchievedHistoryGoals(): void
    {
        $exercise = new Exercise();
        $historyGoal = $this->weightGoal($exercise, target: 50.0, createdAt: '-10 days');
        $activeGoal = $this->weightGoal($exercise, target: 200.0, createdAt: '-1 day');

        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findSessionHistoryForExerciseAndUser')->willReturn([$this->row(100.0)]);

        $resolver = new GoalStateResolver(new GoalProgressResolver($exerciseSetRepository));
        $active = $resolver->findActiveGoal($this->createStub(User::class), [$historyGoal, $activeGoal]);

        self::assertSame($activeGoal, $active);
    }

    public function testFindActiveGoalReturnsNullWhenEveryGoalIsAlreadyAchieved(): void
    {
        $exercise = new Exercise();
        $historyGoal = $this->weightGoal($exercise, target: 50.0, createdAt: '-10 days');

        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findSessionHistoryForExerciseAndUser')->willReturn([$this->row(100.0)]);

        $resolver = new GoalStateResolver(new GoalProgressResolver($exerciseSetRepository));
        $active = $resolver->findActiveGoal($this->createStub(User::class), [$historyGoal]);

        self::assertNull($active);
    }

    private function weightGoal(Exercise $exercise, float $target, string $createdAt): ExerciseGoal
    {
        $goal = new ExerciseGoal();
        $goal->owner = $this->createStub(User::class);
        $goal->exercise = $exercise;
        $goal->measurementType = MeasurementType::WEIGHT_REPS;
        $goal->targetWeight = $target;
        (new \ReflectionProperty($goal, 'createdAt'))->setValue($goal, new \DateTimeImmutable($createdAt));

        return $goal;
    }

    /**
     * @return array{workoutId: string, performedAt: \DateTimeImmutable, weight: float, reps: int, duration: ?int, distance: ?int}
     */
    private function row(float $weight): array
    {
        return [
            'workoutId' => 'workout-a',
            'performedAt' => new \DateTimeImmutable('now'),
            'weight' => $weight,
            'reps' => 5,
            'duration' => null,
            'distance' => null,
        ];
    }
}
