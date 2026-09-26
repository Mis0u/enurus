<?php

declare(strict_types=1);

namespace App\Service\Workout\Draft;

/**
 * Lit le brouillon envoyé par le navigateur. Une structure inattendue est rejetée, mais une valeur
 * de série illisible devient simplement un champ vide : le brouillon ne sert qu'à pré-remplir le
 * formulaire, la validation reste celle de l'enregistrement de la séance.
 *
 * @phpstan-import-type DraftSet from WorkoutDraftExercise
 */
final class WorkoutDraftPayloadParser
{
    private const int MAX_EXERCISES = 50;

    private const int MAX_SETS_PER_EXERCISE = 50;

    /**
     * @return list<WorkoutDraftExercise>
     *
     * @throws InvalidWorkoutDraftException
     */
    public function parse(string $json): array
    {
        try {
            $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidWorkoutDraftException('Malformed workout draft.', 0, $exception);
        }

        if (! \is_array($payload) || ! \is_array($payload['exercises'] ?? null)) {
            throw new InvalidWorkoutDraftException('Workout draft without exercises.');
        }

        return array_map(
            $this->parseExercise(...),
            array_values(\array_slice($payload['exercises'], 0, self::MAX_EXERCISES)),
        );
    }

    private function parseExercise(mixed $exercise): WorkoutDraftExercise
    {
        if (! \is_array($exercise) || ! \is_string($exercise['exerciseId'] ?? null) || ! \is_array($exercise['sets'] ?? null)) {
            throw new InvalidWorkoutDraftException('Malformed workout draft exercise.');
        }

        return new WorkoutDraftExercise(
            $exercise['exerciseId'],
            array_map($this->parseSet(...), array_values(\array_slice($exercise['sets'], 0, self::MAX_SETS_PER_EXERCISE))),
            true === ($exercise['prefilled'] ?? false),
        );
    }

    /**
     * @return DraftSet
     */
    private function parseSet(mixed $set): array
    {
        if (! \is_array($set)) {
            throw new InvalidWorkoutDraftException('Malformed workout draft set.');
        }

        $weight = $this->number($set['weight'] ?? null);

        return [
            'weight' => null === $weight ? null : (float) $weight,
            'reps' => $this->integer($set['reps'] ?? null),
            'duration' => $this->integer($set['duration'] ?? null),
            'distance' => $this->integer($set['distance'] ?? null),
        ];
    }

    private function integer(mixed $value): ?int
    {
        $number = $this->number($value);

        return null === $number ? null : (int) $number;
    }

    private function number(mixed $value): int|float|null
    {
        return \is_int($value) || \is_float($value) ? $value : null;
    }
}
