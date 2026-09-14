<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Goal;

use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Service\Goal\GoalProgress;
use PHPUnit\Framework\TestCase;

final class GoalProgressTest extends TestCase
{
    public function testWeightGoalNotYetAchieved(): void
    {
        $goal = $this->weightGoal(target: 130.0);

        $progress = new GoalProgress($goal, [
            $this->row(weight: 100.0),
            $this->row(weight: 120.0),
        ]);

        self::assertFalse($progress->achieved);
        self::assertSame(120.0, $progress->currentValue);
        self::assertSame(92, $progress->percent);
        self::assertNull($progress->achievedAt);
    }

    public function testWeightGoalAchievedOnFirstCrossing(): void
    {
        $goal = $this->weightGoal(target: 100.0);
        $crossingDate = new \DateTimeImmutable('2026-03-10');

        $progress = new GoalProgress($goal, [
            $this->row(weight: 90.0, performedAt: new \DateTimeImmutable('2026-03-01')),
            $this->row(weight: 100.0, performedAt: $crossingDate),
            $this->row(weight: 110.0, performedAt: new \DateTimeImmutable('2026-03-20')),
        ]);

        self::assertTrue($progress->achieved);
        self::assertSame(110.0, $progress->currentValue);
        self::assertSame(100, $progress->percent);
        self::assertSame($crossingDate, $progress->achievedAt);
    }

    /**
     * Un objectif redevient "en cours" si la séance à l'origine du franchissement est retirée —
     * le calcul est toujours dérivé, jamais un flag persisté (voir `GoalProgressResolver`).
     */
    public function testGoalRevertsToInProgressWhenAchievingSessionIsRemoved(): void
    {
        $goal = $this->weightGoal(target: 100.0);

        $progress = new GoalProgress($goal, [
            $this->row(weight: 90.0),
        ]);

        self::assertFalse($progress->achieved);
    }

    public function testDualTargetGoalNotYetAchieved(): void
    {
        $goal = $this->weightRepsGoal(targetWeight: 100.0, targetReps: 6);

        $progress = new GoalProgress($goal, [
            $this->row(weight: 100.0, reps: 4),
            $this->row(weight: 90.0, reps: 6),
        ]);

        self::assertFalse($progress->achieved);
        self::assertNull($progress->achievedAt);
    }

    /**
     * Une série à 110kg × 8 reps (poids max) et une autre à 80kg × 10 reps (reps max) ne doivent
     * jamais se combiner pour simuler un faux "100kg × 6 reps atteint" — seule une série qui
     * satisfait les deux dimensions EN MÊME TEMPS compte.
     */
    public function testDualTargetGoalNeverCombinesTwoDifferentSessions(): void
    {
        $goal = $this->weightRepsGoal(targetWeight: 100.0, targetReps: 6);

        $progress = new GoalProgress($goal, [
            $this->row(weight: 110.0, reps: 3),
            $this->row(weight: 60.0, reps: 10),
        ]);

        self::assertFalse($progress->achieved);
    }

    public function testDualTargetGoalAchievedOnFirstCrossing(): void
    {
        $goal = $this->weightRepsGoal(targetWeight: 100.0, targetReps: 6);
        $crossingDate = new \DateTimeImmutable('2026-04-05');

        $progress = new GoalProgress($goal, [
            $this->row(weight: 90.0, reps: 8, performedAt: new \DateTimeImmutable('2026-04-01')),
            $this->row(weight: 100.0, reps: 6, performedAt: $crossingDate),
            $this->row(weight: 105.0, reps: 6, performedAt: new \DateTimeImmutable('2026-04-10')),
        ]);

        self::assertTrue($progress->achieved);
        self::assertSame($crossingDate, $progress->achievedAt);
        self::assertSame(105.0, $progress->currentValue);
        self::assertSame(6, $progress->currentReps);
        self::assertSame(100, $progress->percent);
    }

    public function testDurationGoalUsesDurationField(): void
    {
        $goal = new ExerciseGoal();
        $goal->owner = $this->createStub(\App\Entity\User::class);
        $goal->exercise = new Exercise();
        $goal->measurementType = MeasurementType::TIME;
        $goal->targetDuration = 60;

        $progress = new GoalProgress($goal, [
            $this->row(weight: 0.0, duration: 45),
            $this->row(weight: 0.0, duration: 65),
        ]);

        self::assertTrue($progress->achieved);
        self::assertSame(65.0, $progress->currentValue);
    }

    /**
     * "8 min à 30 kg" : une série ne compte que si la durée ET la charge additionnelle sont
     * atteintes en même temps — même principe que le dual reps/poids sur `WEIGHT_REPS`.
     */
    public function testDurationWithAddedWeightGoalAchievedOnFirstCrossing(): void
    {
        $goal = $this->durationGoal(targetDuration: 480, targetWeight: 30.0);
        $crossingDate = new \DateTimeImmutable('2026-05-05');

        $progress = new GoalProgress($goal, [
            $this->row(weight: 20.0, duration: 500, performedAt: new \DateTimeImmutable('2026-05-01')),
            $this->row(weight: 30.0, duration: 480, performedAt: $crossingDate),
        ]);

        self::assertTrue($progress->achieved);
        self::assertSame($crossingDate, $progress->achievedAt);
        self::assertSame(480.0, $progress->currentValue);
        self::assertSame(30.0, $progress->currentSecondaryWeight);
        self::assertNull($progress->currentReps);
    }

    public function testDurationWithAddedWeightGoalNeverCombinesTwoDifferentSessions(): void
    {
        $goal = $this->durationGoal(targetDuration: 480, targetWeight: 30.0);

        $progress = new GoalProgress($goal, [
            $this->row(weight: 20.0, duration: 600),
            $this->row(weight: 40.0, duration: 300),
        ]);

        self::assertFalse($progress->achieved);
    }

    /**
     * "2000 m à 20 kg" — même principe que pour `TIME`, appliqué à `DISTANCE`.
     */
    public function testDistanceWithAddedWeightGoalAchievedOnFirstCrossing(): void
    {
        $goal = $this->distanceGoal(targetDistance: 2000, targetWeight: 20.0);
        $crossingDate = new \DateTimeImmutable('2026-06-05');

        $progress = new GoalProgress($goal, [
            $this->row(weight: 10.0, distance: 2500, performedAt: new \DateTimeImmutable('2026-06-01')),
            $this->row(weight: 20.0, distance: 2000, performedAt: $crossingDate),
        ]);

        self::assertTrue($progress->achieved);
        self::assertSame($crossingDate, $progress->achievedAt);
        self::assertSame(2000.0, $progress->currentValue);
        self::assertSame(20.0, $progress->currentSecondaryWeight);
    }

    public function testWeightRepsGoalWithoutTargetRepsIgnoresRepsField(): void
    {
        $goal = $this->weightGoal(target: 100.0);

        $progress = new GoalProgress($goal, [
            $this->row(weight: 100.0, reps: 1),
        ]);

        self::assertTrue($progress->achieved);
        self::assertNull($progress->currentReps);
        self::assertNull($progress->currentSecondaryWeight);
    }

    private function durationGoal(int $targetDuration, ?float $targetWeight = null): ExerciseGoal
    {
        $goal = new ExerciseGoal();
        $goal->owner = $this->createStub(\App\Entity\User::class);
        $goal->exercise = new Exercise();
        $goal->measurementType = MeasurementType::TIME;
        $goal->targetDuration = $targetDuration;
        $goal->targetWeight = $targetWeight;

        return $goal;
    }

    private function distanceGoal(int $targetDistance, ?float $targetWeight = null): ExerciseGoal
    {
        $goal = new ExerciseGoal();
        $goal->owner = $this->createStub(\App\Entity\User::class);
        $goal->exercise = new Exercise();
        $goal->measurementType = MeasurementType::DISTANCE;
        $goal->targetDistance = $targetDistance;
        $goal->targetWeight = $targetWeight;

        return $goal;
    }

    private function weightGoal(float $target): ExerciseGoal
    {
        $goal = new ExerciseGoal();
        $goal->owner = $this->createStub(\App\Entity\User::class);
        $goal->exercise = new Exercise();
        $goal->measurementType = MeasurementType::WEIGHT_REPS;
        $goal->targetWeight = $target;

        return $goal;
    }

    private function weightRepsGoal(float $targetWeight, int $targetReps): ExerciseGoal
    {
        $goal = $this->weightGoal($targetWeight);
        $goal->targetReps = $targetReps;

        return $goal;
    }

    /**
     * @return array{workoutId: string, performedAt: \DateTimeImmutable, weight: float, reps: int, duration: ?int, distance: ?int}
     */
    private function row(
        float $weight,
        int $reps = 5,
        ?int $duration = null,
        ?int $distance = null,
        ?\DateTimeImmutable $performedAt = null,
    ): array {
        return [
            'workoutId' => 'workout-' . spl_object_id(new \stdClass()),
            'performedAt' => $performedAt ?? new \DateTimeImmutable('now'),
            'weight' => $weight,
            'reps' => $reps,
            'duration' => $duration,
            'distance' => $distance,
        ];
    }
}
