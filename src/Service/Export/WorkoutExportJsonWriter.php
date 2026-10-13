<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Enum\Export\WorkoutExportFormatEnum;
use App\Service\Exercise\ExerciseNameTranslator;

/**
 * Export JSON des séances : données brutes et structurées (séance → exercices → séries), fidèles au
 * stockage — poids toujours en kg, dates murales sans fuseau (comme `Workout::$performedAt`),
 * valeurs d'énumération non traduites. Pour une autre appli ou un script (portabilité RGPD), donc
 * compact : il est lu par un logiciel, pas à l'œil.
 *
 * Écrit séance par séance (un `json_encode` par séance, jamais de tout l'historique d'un coup) : la
 * mémoire reste celle d'une séance. L'enveloppe du document est donc écrite à la main.
 */
final readonly class WorkoutExportJsonWriter implements WorkoutExportWriter
{
    private const int JSON_FLAGS = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR;

    public function __construct(
        private ExerciseNameTranslator $exerciseNameTranslator,
    ) {
    }

    public function format(): WorkoutExportFormatEnum
    {
        return WorkoutExportFormatEnum::JSON;
    }

    /**
     * Poids toujours en kg : l'unité de `$user` ne sert qu'à l'export CSV. `$locale` : langue des
     * noms d'exercices publics, seule valeur traduite.
     */
    public function write(User $user, iterable $workouts, $stream, string $locale): void
    {
        fwrite($stream, \sprintf('{"exportedAt":%s,"weightUnit":"kg","workouts":[', $this->encode(new \DateTimeImmutable()->format(\DATE_ATOM))));

        $separator = '';
        foreach ($workouts as $workout) {
            fwrite($stream, $separator . $this->encode($this->workoutData($workout, $locale)));
            $separator = ',';
        }

        fwrite($stream, ']}');
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, self::JSON_FLAGS);
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
