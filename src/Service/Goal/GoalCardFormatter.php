<?php

declare(strict_types=1);

namespace App\Service\Goal;

use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Entity\User;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Service\Utils\WeightConverterService;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Traduit un `GoalProgress` (données brutes) en libellés affichables — kg/lbs pour un exercice
 * poids/reps, secondes/mètres traduits pour les autres. Partagé entre le widget dashboard, le
 * bloc objectif de la page historique et la notification "objectif atteint".
 */
final readonly class GoalCardFormatter
{
    public function __construct(
        private WeightConverterService $weightConverterService,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{exerciseName: string, currentLabel: string, targetLabel: string, percent: int, achieved: bool, achievedAt: ?\DateTimeImmutable, exerciseId: string}
     */
    public function format(GoalProgress $progress, User $user): array
    {
        $goal = $progress->goal;

        if (null === $goal->exercise->id) {
            throw new \LogicException('Cannot format a goal card for an exercise without a persisted id.');
        }

        return [
            'exerciseId' => $goal->exercise->id->toRfc4122(),
            'exerciseName' => $this->exerciseName($goal->exercise),
            'currentLabel' => $this->valueLabel($progress->currentValue, $goal->measurementType, $user, $progress->currentReps, $progress->currentSecondaryWeight, $this->isBodyweight($goal->exercise)),
            'targetLabel' => $this->formatTarget($goal, $user),
            'percent' => $progress->percent,
            'achieved' => $progress->achieved,
            'achievedAt' => $progress->achievedAt,
        ];
    }

    public function formatTarget(ExerciseGoal $goal, User $user): string
    {
        return $this->valueLabel($goal->targetValue(), $goal->measurementType, $user, $goal->targetReps, $goal->targetWeight, $this->isBodyweight($goal->exercise));
    }

    /**
     * Le nom d'un exercice public est une clé de traduction (domaine `exercise`), celui d'un
     * exercice personnel est du texte libre — même convention que `exercise/list/_exercise_card.html.twig`
     * et `exercise/history/index.html.twig`.
     */
    public function exerciseName(Exercise $exercise): string
    {
        return $exercise->isPublic ? $this->translator->trans($exercise->name, [], 'exercise') : $exercise->name;
    }

    /**
     * Le poids cible/actuel d'un exercice au poids de corps est TOUJOURS le lest seul (voir
     * `GoalProgress` et `ExerciseSetRepository::findSessionHistoryForExerciseAndUser()`) — la
     * mention "de lest" évite qu'un utilisateur croie que ce chiffre inclut son poids de corps.
     */
    private function isBodyweight(Exercise $exercise): bool
    {
        return null !== $exercise->bodyweightPercent;
    }

    /**
     * `$reps` n'est pertinent que pour `WEIGHT_REPS` (objectif à deux dimensions "X reps à Y kg").
     * `$secondaryWeight` n'est pertinent que pour `TIME`/`DISTANCE` (objectif à deux dimensions
     * "X (durée/distance) à Y kg" — jamais utilisé pour `WEIGHT_REPS`, où le poids est déjà la
     * métrique principale, pas une charge additionnelle optionnelle). `$isBodyweight` ajoute la
     * mention "de lest" sur le poids d'un exercice au poids de corps (jamais pertinent pour
     * `TIME`/`DISTANCE`, qui n'ont jamais de `bodyweightPercent`).
     */
    private function valueLabel(float $value, MeasurementType $type, User $user, ?int $reps = null, ?float $secondaryWeight = null, bool $isBodyweight = false): string
    {
        if (MeasurementType::WEIGHT_REPS === $type && null !== $reps) {
            return $this->translator->trans('exercise.goal.target_weight_reps', [
                'weight' => $this->weightLabel($value, $user, $isBodyweight),
                'reps' => $reps,
                'repsUnit' => $this->translator->trans('workout.reps_abbreviate', [], 'navigation'),
            ], 'navigation');
        }

        if (MeasurementType::TIME === $type && null !== $secondaryWeight) {
            return $this->translator->trans('exercise.goal.target_duration_weight', [
                'duration' => $this->translator->trans('exercise.goal.target_duration', [
                    'seconds' => (int) $value,
                ], 'navigation'),
                'weight' => $this->weightConverterService->format($secondaryWeight, $user->unitOfMeasure),
            ], 'navigation');
        }

        if (MeasurementType::DISTANCE === $type && null !== $secondaryWeight) {
            return $this->translator->trans('exercise.goal.target_distance_weight', [
                'distance' => $this->translator->trans('exercise.goal.target_distance', [
                    'meters' => (int) $value,
                ], 'navigation'),
                'weight' => $this->weightConverterService->format($secondaryWeight, $user->unitOfMeasure),
            ], 'navigation');
        }

        return match ($type) {
            MeasurementType::WEIGHT_REPS => $this->weightLabel($value, $user, $isBodyweight),
            MeasurementType::TIME => $this->translator->trans('exercise.goal.target_duration', [
                'seconds' => (int) $value,
            ], 'navigation'),
            MeasurementType::DISTANCE => $this->translator->trans('exercise.goal.target_distance', [
                'meters' => (int) $value,
            ], 'navigation'),
        };
    }

    private function weightLabel(float $kg, User $user, bool $isBodyweight): string
    {
        $formatted = $this->weightConverterService->format($kg, $user->unitOfMeasure);

        return $isBodyweight
            ? $this->translator->trans('exercise.goal.target_added_weight_suffix', [
                'weight' => $formatted,
            ], 'navigation')
            : $formatted;
    }
}
