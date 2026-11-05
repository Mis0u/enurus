<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OneRepMax;

use App\Entity\Exercise;
use App\Entity\User;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Repository\ExerciseSetRepository;
use App\Service\OneRepMax\ExerciseOneRepMaxEstimator;
use App\Service\OneRepMax\OneRepMaxCalculator;
use App\Service\Utils\WeightConverterService;
use PHPUnit\Framework\TestCase;

final class ExerciseOneRepMaxEstimatorTest extends TestCase
{
    public function testKeepsTheSetGivingTheHighestEstimateRatherThanTheHeaviestOne(): void
    {
        $estimate = $this->estimator([
            $this->rawSet(weight: 100.0, reps: 1, performedAt: '2026-10-01'),
            $this->rawSet(weight: 90.0, reps: 5, performedAt: '2026-10-08'),
        ])->estimate($this->user(UnitOfMeasureEnum::KG), $this->exercise());

        self::assertNotNull($estimate);
        self::assertEqualsWithDelta(105.0, $estimate->value, 0.01);
        self::assertSame(90.0, $estimate->weight);
        self::assertSame(5, $estimate->reps);
        self::assertEquals(new \DateTimeImmutable('2026-10-08'), $estimate->performedAt);
    }

    public function testAnEqualLaterEstimateKeepsTheFirstSetThatReachedIt(): void
    {
        $estimate = $this->estimator([
            $this->rawSet(weight: 80.0, reps: 6, performedAt: '2026-10-01'),
            $this->rawSet(weight: 80.0, reps: 6, performedAt: '2026-10-08'),
        ])->estimate($this->user(UnitOfMeasureEnum::KG), $this->exercise());

        self::assertNotNull($estimate);
        self::assertEquals(new \DateTimeImmutable('2026-10-01'), $estimate->performedAt);
    }

    public function testIgnoresSetsTooLongForAReliableEstimate(): void
    {
        $estimate = $this->estimator([
            $this->rawSet(weight: 60.0, reps: 15),
        ])->estimate($this->user(UnitOfMeasureEnum::KG), $this->exercise());

        self::assertNull($estimate);
    }

    public function testShowsTheEstimateInTheUserUnit(): void
    {
        $estimate = $this->estimator([
            $this->rawSet(weight: 80.0, reps: 6),
        ])->estimate($this->user(UnitOfMeasureEnum::LBS), $this->exercise());

        self::assertNotNull($estimate);
        self::assertEqualsWithDelta(211.6, $estimate->value, 0.1);
        self::assertEqualsWithDelta(176.4, $estimate->weight, 0.1);
    }

    public function testABodyweightExerciseIsEstimatedOnItsRealLoad(): void
    {
        // Tractions à 78 kg (92 % soulevés) + 10 kg de lest : 81,76 kg réels × 6.
        $estimate = $this->estimator([
            $this->rawSet(weight: 81.76, reps: 6, addedWeight: 10.0),
        ])->estimate($this->user(UnitOfMeasureEnum::KG, bodyweightKg: 78.0), $this->bodyweightExercise());

        self::assertNotNull($estimate);
        self::assertEqualsWithDelta(98.1, $estimate->value, 0.05);
        self::assertTrue($estimate->includesBodyweight);
    }

    public function testALoadedExerciseDoesNotIncludeBodyweight(): void
    {
        $estimate = $this->estimator([
            $this->rawSet(weight: 80.0, reps: 6),
        ])->estimate($this->user(UnitOfMeasureEnum::KG), $this->exercise());

        self::assertNotNull($estimate);
        self::assertFalse($estimate->includesBodyweight);
    }

    /**
     * Série enregistrée avant que le poids de corps soit figé sur chaque série : seul le lest y
     * figure, l'estimer sous-évaluerait le 1RM.
     */
    public function testABodyweightSetWithoutItsBodyweightIsLeftOut(): void
    {
        $estimate = $this->estimator([
            $this->rawSet(weight: 20.0, reps: 5, addedWeight: 20.0),
        ])->estimate($this->user(UnitOfMeasureEnum::KG, bodyweightKg: 78.0), $this->bodyweightExercise());

        self::assertNull($estimate);
    }

    public function testABodyweightExerciseHasNoEstimateWithoutTheUserBodyweight(): void
    {
        $estimate = $this->estimator([
            $this->rawSet(weight: 81.76, reps: 6, addedWeight: 10.0),
        ])->estimate($this->user(UnitOfMeasureEnum::KG), $this->bodyweightExercise());

        self::assertNull($estimate);
    }

    public function testATimedExerciseHasNoEstimate(): void
    {
        $exercise = $this->exercise();
        $exercise->measurementType = MeasurementType::TIME;

        $estimate = $this->estimator([
            $this->rawSet(weight: 10.0, reps: 1),
        ])->estimate($this->user(UnitOfMeasureEnum::KG), $exercise);

        self::assertNull($estimate);
    }

    public function testAnExerciseWithoutHistoryHasNoEstimate(): void
    {
        self::assertNull($this->estimator([])->estimate($this->user(UnitOfMeasureEnum::KG), $this->exercise()));
    }

    /**
     * @param list<array{workoutId: string, performedAt: \DateTimeImmutable, weight: float, addedWeight: float, reps: int, duration: ?int, distance: ?int}> $rawSets
     */
    private function estimator(array $rawSets): ExerciseOneRepMaxEstimator
    {
        $repository = $this->createStub(ExerciseSetRepository::class);
        $repository->method('findSessionHistoryForExerciseAndUser')->willReturn($rawSets);

        return new ExerciseOneRepMaxEstimator($repository, new OneRepMaxCalculator(), new WeightConverterService());
    }

    /**
     * @return array{workoutId: string, performedAt: \DateTimeImmutable, weight: float, addedWeight: float, reps: int, duration: ?int, distance: ?int}
     */
    private function rawSet(float $weight, int $reps, string $performedAt = '2026-10-01', ?float $addedWeight = null): array
    {
        return [
            'workoutId' => 'workout-' . $performedAt,
            'performedAt' => new \DateTimeImmutable($performedAt),
            'weight' => $weight,
            'addedWeight' => $addedWeight ?? $weight,
            'reps' => $reps,
            'duration' => null,
            'distance' => null,
        ];
    }

    private function exercise(): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Bench press';
        $exercise->measurementType = MeasurementType::WEIGHT_REPS;

        return $exercise;
    }

    private function bodyweightExercise(): Exercise
    {
        $exercise = $this->exercise();
        $exercise->bodyweightPercent = 92.0;

        return $exercise;
    }

    private function user(UnitOfMeasureEnum $unit, ?float $bodyweightKg = null): User
    {
        $user = new User();
        $user->unitOfMeasure = $unit;
        $user->bodyweightKg = $bodyweightKg;

        return $user;
    }
}
