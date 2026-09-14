<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Entity\User;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\ExerciseGoalRepository;
use App\Repository\ExerciseSetRepository;
use App\Service\Dashboard\DashboardGoalService;
use App\Service\Goal\GoalProgressResolver;
use App\Service\Goal\GoalStateResolver;
use PHPUnit\Framework\TestCase;

final class DashboardGoalServiceTest extends TestCase
{
    public function testGoalsAreSplitBetweenCurrentAndAchieved(): void
    {
        $inProgress = $this->weightGoal(target: 200.0);
        $achieved = $this->weightGoal(target: 50.0);

        $exerciseGoalRepository = $this->createStub(ExerciseGoalRepository::class);
        $exerciseGoalRepository->method('findByOwner')->willReturn([$inProgress, $achieved]);

        $exerciseSetRepository = $this->createStub(ExerciseSetRepository::class);
        $exerciseSetRepository->method('findSessionHistoryForExerciseAndUser')->willReturn([
            $this->row(100.0),
        ]);

        $goalStateResolver = new GoalStateResolver(new GoalProgressResolver($exerciseSetRepository));
        $state = (new DashboardGoalService($exerciseGoalRepository, $goalStateResolver))
            ->getStateForUser($this->createStub(User::class));

        self::assertCount(1, $state->current);
        self::assertSame($inProgress, $state->current[0]->goal);
        self::assertCount(1, $state->achieved);
        self::assertSame($achieved, $state->achieved[0]->goal);
    }

    private function weightGoal(float $target): ExerciseGoal
    {
        $goal = new ExerciseGoal();
        $goal->owner = $this->createStub(User::class);
        $goal->exercise = new Exercise();
        $goal->measurementType = MeasurementType::WEIGHT_REPS;
        $goal->targetWeight = $target;
        (new \ReflectionProperty($goal, 'createdAt'))->setValue($goal, new \DateTimeImmutable('now'));

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
