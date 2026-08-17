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
        $weight = round($this->convertToLbs($weightKg, $unit), 1);
        return \sprintf('%s %s', $weight, $unit->value);
    }

    /**
     * Reconvertit in-place tous les ExerciseSet du Workout : lbs → kg avant persist.
     * Sans effet si l'unité est déjà KG.
     *
     * `$previousWeightsById` (poids déjà en kg, capturé avant `$form->handleRequest()`) permet
     * de ne jamais reconvertir une série dont le poids ressort de la soumission strictement
     * identique à sa valeur avant soumission : c'est le signe que le champ a été laissé vide et
     * que le setter défensif `ExerciseSet::weight` a gardé l'ancienne valeur (déjà en kg) —
     * la reconvertir la traiterait à tort comme un lbs fraîchement saisi et la corromprait
     * (double conversion). Une série absente de `$previousWeightsById` (nouvelle série) est
     * toujours convertie normalement.
     *
     * @param array<string, float> $previousWeightsById poids en kg avant soumission, keyé par ExerciseSet::id
     */
    public function convertWorkoutSetsToKg(Workout $workout, UnitOfMeasureEnum $unit, array $previousWeightsById = []): void
    {
        if (UnitOfMeasureEnum::KG === $unit) {
            return;
        }

        foreach ($workout->workoutExercises as $workoutExercise) {
            foreach ($workoutExercise->exerciseSets as $set) {
                $previousWeight = null !== $set->id ? ($previousWeightsById[(string) $set->id] ?? null) : null;

                if (null !== $previousWeight && $previousWeight === $set->weight) {
                    continue;
                }

                $set->weight = $this->convertToKg($set->weight, $unit);
            }
        }
    }
}
