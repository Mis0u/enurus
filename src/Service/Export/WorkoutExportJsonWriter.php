<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\ExerciseSet;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Service\Exercise\ExerciseNameTranslator;

/**
 * Export JSON des séances : données brutes et structurées (séance → exercices → séries), fidèles au
 * stockage — poids toujours en kg, dates murales sans fuseau (comme `Workout::$performedAt`),
 * valeurs d'énumération non traduites. Pour une autre appli ou un script (portabilité RGPD).
 */
final readonly class WorkoutExportJsonWriter
{
    public function __construct(
        private ExerciseNameTranslator $exerciseNameTranslator,
    ) {
    }

    /**
     * `$locale` : langue des noms d'exercices publics (seule valeur traduite), explicite car écrit
     * dans une réponse streamée, après le traitement de la requête.
     *
     * @param list<Workout> $workouts
     * @param resource      $stream
     */
    public function write(array $workouts, $stream, string $locale): void
    {
        fwrite($stream, json_encode([
            'exportedAt' => new \DateTimeImmutable()->format(\DATE_ATOM),
            'weightUnit' => 'kg',
            'workouts' => array_map(fn (Workout $workout): array => $this->workoutData($workout, $locale), $workouts),
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function workoutData(Workout $workout, string $locale): array
    {
        return [
            'performedAt' => $workout->performedAt->format('Y-m-d\TH:i:s'),
            'durationMinutes' => $workout->duration,
            'routine' => $workout->routine?->name,
            'mood' => $workout->mood?->value,
            'note' => $workout->note,
            'exercises' => array_values(array_map(
                fn (WorkoutExercise $workoutExercise): array => $this->exerciseData($workoutExercise, $locale),
                $workout->workoutExercises->toArray(),
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exerciseData(WorkoutExercise $workoutExercise, string $locale): array
    {
        $exercise = $workoutExercise->exercise;

        return [
            'name' => $this->exerciseNameTranslator->translate($exercise, $locale),
            'measurementType' => $exercise->measurementType->value,
            // Non nul : exercice au poids du corps, `weightKg` des séries n'est alors que le lest.
            'bodyweightPercent' => $exercise->bodyweightPercent,
            'sets' => array_values(array_map(
                static fn (ExerciseSet $set): array => self::setData($set, $exercise->measurementType),
                $workoutExercise->exerciseSets->toArray(),
            )),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function setData(ExerciseSet $set, MeasurementType $type): array
    {
        return [
            'weightKg' => $set->weight,
            'reps' => MeasurementType::WEIGHT_REPS === $type ? $set->reps : null,
            'durationSeconds' => MeasurementType::TIME === $type ? $set->duration : null,
            'distanceMeters' => MeasurementType::DISTANCE === $type ? $set->distance : null,
        ];
    }
}
