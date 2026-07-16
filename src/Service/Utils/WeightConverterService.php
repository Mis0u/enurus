<?php

declare(strict_types=1);

namespace App\Service\Utils;

use App\Entity\Workout;
use App\Enum\Entity\User\UnitOfMeasureEnum;

final class WeightConverterService
{
    public function convertToLbs(float $weightKg, UnitOfMeasureEnum $unit): float
    {
        return match ($unit) {
            UnitOfMeasureEnum::KG => $weightKg,
            UnitOfMeasureEnum::LBS => round($weightKg * UnitOfMeasureEnum::WEIGHT_IN_LBS, 1),
        };
    }

    public function convertToKg(float $weight, UnitOfMeasureEnum $unit): float
    {
        return match ($unit) {
            UnitOfMeasureEnum::KG => $weight,
            UnitOfMeasureEnum::LBS => round($weight / UnitOfMeasureEnum::WEIGHT_IN_LBS, 2),
        };
    }

    public function format(float $weightKg, UnitOfMeasureEnum $unit): string
    {
        $weight = $this->convertToLbs($weightKg, $unit);
        return \sprintf('%s %s', $weight, $unit->value);
    }

    /**
     * Reconvertit in-place tous les ExerciseSet du Workout : lbs → kg avant persist.
     * Sans effet si l'unité est déjà KG.
     */
    public function convertWorkoutSetsToKg(Workout $workout, UnitOfMeasureEnum $unit): void
    {
        if (UnitOfMeasureEnum::KG === $unit) {
            return;
        }

        foreach ($workout->workoutExercises as $workoutExercise) {
            foreach ($workoutExercise->exerciseSets as $set) {
                $set->weight = $this->convertToKg($set->weight, $unit);
            }
        }
    }
}
