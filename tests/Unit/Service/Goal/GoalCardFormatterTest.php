<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Goal;

use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Entity\User;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Service\Goal\GoalCardFormatter;
use App\Service\Goal\GoalProgress;
use App\Service\Utils\WeightConverterService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GoalCardFormatterTest extends TestCase
{
    public function testFormatTargetUsesWeightOnlyLabelWithoutTargetReps(): void
    {
        $goal = $this->weightGoal(targetWeight: 100.0);

        $label = $this->formatter()->formatTarget($goal, $this->user());

        self::assertSame('100 kg', $label);
    }

    public function testFormatTargetCombinesWeightAndRepsWhenTargetRepsIsSet(): void
    {
        $goal = $this->weightGoal(targetWeight: 100.0, targetReps: 6);

        $label = $this->formatter()->formatTarget($goal, $this->user());

        self::assertSame('100 kg × 6 Reps', $label);
    }

    public function testFormatCurrentLabelUsesCurrentRepsFromProgress(): void
    {
        $goal = $this->weightGoal(targetWeight: 100.0, targetReps: 6);
        $goal->exercise->name = 'Développé couché';
        $goal->exercise->isPublic = false;

        $progress = new GoalProgress($goal, [
            [
                'workoutId' => 'workout-1',
                'performedAt' => new \DateTimeImmutable('2026-01-01'),
                'weight' => 90.0,
                'reps' => 6,
                'duration' => null,
                'distance' => null,
            ],
        ]);

        $card = $this->formatter()->format($progress, $this->user());

        self::assertSame('90 kg × 6 Reps', $card['currentLabel']);
        self::assertSame('100 kg × 6 Reps', $card['targetLabel']);
    }

    public function testFormatTargetCombinesDurationAndAddedWeightWhenTargetWeightIsSet(): void
    {
        $goal = $this->durationGoal(targetDuration: 480, targetWeight: 30.0);

        $label = $this->formatter()->formatTarget($goal, $this->user());

        self::assertSame('480 s × 30 kg', $label);
    }

    public function testFormatTargetCombinesDistanceAndAddedWeightWhenTargetWeightIsSet(): void
    {
        $goal = $this->distanceGoal(targetDistance: 2000, targetWeight: 20.0);

        $label = $this->formatter()->formatTarget($goal, $this->user());

        self::assertSame('2000 m × 20 kg', $label);
    }

    private function formatter(): GoalCardFormatter
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $id, array $parameters = []) {
                return match ($id) {
                    'workout.reps_abbreviate' => 'Reps',
                    'exercise.goal.target_weight_reps' => \sprintf('%s × %d %s', $parameters['weight'], $parameters['reps'], $parameters['repsUnit']),
                    'exercise.goal.target_duration' => \sprintf('%d s', $parameters['seconds']),
                    'exercise.goal.target_distance' => \sprintf('%d m', $parameters['meters']),
                    'exercise.goal.target_duration_weight', 'exercise.goal.target_distance_weight' => \sprintf('%s × %s', $parameters['duration'] ?? $parameters['distance'], $parameters['weight']),
                    default => $id,
                };
            },
        );

        return new GoalCardFormatter(new WeightConverterService(), $translator);
    }

    private function user(): User
    {
        return new User();
    }

    private function weightGoal(float $targetWeight, ?int $targetReps = null): ExerciseGoal
    {
        $goal = new ExerciseGoal();
        $goal->owner = $this->createStub(User::class);
        $goal->exercise = new Exercise();
        $goal->exercise->id = Uuid::v7();
        $goal->measurementType = MeasurementType::WEIGHT_REPS;
        $goal->targetWeight = $targetWeight;
        $goal->targetReps = $targetReps;

        return $goal;
    }

    private function durationGoal(int $targetDuration, ?float $targetWeight = null): ExerciseGoal
    {
        $goal = new ExerciseGoal();
        $goal->owner = $this->createStub(User::class);
        $goal->exercise = new Exercise();
        $goal->exercise->id = Uuid::v7();
        $goal->measurementType = MeasurementType::TIME;
        $goal->targetDuration = $targetDuration;
        $goal->targetWeight = $targetWeight;

        return $goal;
    }

    private function distanceGoal(int $targetDistance, ?float $targetWeight = null): ExerciseGoal
    {
        $goal = new ExerciseGoal();
        $goal->owner = $this->createStub(User::class);
        $goal->exercise = new Exercise();
        $goal->exercise->id = Uuid::v7();
        $goal->measurementType = MeasurementType::DISTANCE;
        $goal->targetDistance = $targetDistance;
        $goal->targetWeight = $targetWeight;

        return $goal;
    }
}
