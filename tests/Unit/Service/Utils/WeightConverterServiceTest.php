<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Utils;

use App\Entity\ExerciseSet;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Service\Utils\WeightConverterService;
use PHPUnit\Framework\TestCase;

final class WeightConverterServiceTest extends TestCase
{
    public function testConvertToLbsWithKgUnitReturnsTheWeightUnchanged(): void
    {
        $service = new WeightConverterService();

        self::assertSame(100.0, $service->convertToLbs(100.0, UnitOfMeasureEnum::KG));
    }

    public function testConvertToLbsWithLbsUnitConvertsAndRoundsToOneDecimal(): void
    {
        $service = new WeightConverterService();

        // 100 kg * 2.20462 = 220.462 -> arrondi à 1 décimale.
        self::assertSame(220.5, $service->convertToLbs(100.0, UnitOfMeasureEnum::LBS));
    }

    public function testConvertToKgWithKgUnitReturnsTheWeightUnchanged(): void
    {
        $service = new WeightConverterService();

        self::assertSame(100.0, $service->convertToKg(100.0, UnitOfMeasureEnum::KG));
    }

    public function testConvertToKgWithLbsUnitConvertsAndRoundsToTwoDecimals(): void
    {
        $service = new WeightConverterService();

        // 220.462 lbs / 2.20462 = 100.0 exactement -> arrondi à 2 décimales.
        self::assertSame(100.0, $service->convertToKg(220.462, UnitOfMeasureEnum::LBS));
    }

    public function testRoundTripFromKgToLbsAndBackStaysWithinRoundingTolerance(): void
    {
        $service = new WeightConverterService();

        $lbs = $service->convertToLbs(60.0, UnitOfMeasureEnum::LBS);
        $backToKg = $service->convertToKg($lbs, UnitOfMeasureEnum::LBS);

        // L'aller-retour perd de la précision à cause des deux arrondis successifs
        // (1 décimale puis 2 décimales) : on tolère un écart infime, jamais un écart métier.
        self::assertEqualsWithDelta(60.0, $backToKg, 0.01);
    }

    public function testFormatWithKgUnitAppendsTheUnitSuffix(): void
    {
        $service = new WeightConverterService();

        self::assertSame('100 kg', $service->format(100.0, UnitOfMeasureEnum::KG));
    }

    public function testFormatWithLbsUnitConvertsBeforeAppendingTheUnitSuffix(): void
    {
        $service = new WeightConverterService();

        self::assertSame('220.5 lbs', $service->format(100.0, UnitOfMeasureEnum::LBS));
    }

    public function testConvertWorkoutSetsToKgWithKgUnitLeavesWeightsUntouched(): void
    {
        $service = new WeightConverterService();
        $workout = $this->workoutWithSingleSet(weight: 100.0);

        $service->convertWorkoutSetsToKg($workout, UnitOfMeasureEnum::KG);

        self::assertSame(100.0, $this->firstSetWeight($workout));
    }

    public function testConvertWorkoutSetsToKgWithLbsUnitConvertsEverySetInPlace(): void
    {
        $service = new WeightConverterService();
        $workout = $this->workoutWithSingleSet(weight: 220.462);

        $service->convertWorkoutSetsToKg($workout, UnitOfMeasureEnum::LBS);

        self::assertSame(100.0, $this->firstSetWeight($workout));
    }

    private function workoutWithSingleSet(float $weight): Workout
    {
        $workout = new Workout();
        $workoutExercise = new WorkoutExercise();
        $exerciseSet = new ExerciseSet();
        $exerciseSet->weight = $weight;
        $exerciseSet->reps = 1;

        $workoutExercise->addExerciseSet($exerciseSet);
        $workout->addWorkoutExercise($workoutExercise);

        return $workout;
    }

    private function firstSetWeight(Workout $workout): float
    {
        $workoutExercise = $workout->workoutExercises->first();
        if (! $workoutExercise instanceof WorkoutExercise) {
            throw new \LogicException('Expected a WorkoutExercise.');
        }

        $exerciseSet = $workoutExercise->exerciseSets->first();
        if (! $exerciseSet instanceof ExerciseSet) {
            throw new \LogicException('Expected an ExerciseSet.');
        }

        return $exerciseSet->weight;
    }
}
