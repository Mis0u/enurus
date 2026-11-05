<?php

declare(strict_types=1);

namespace App\Service\OneRepMax;

use App\Entity\Exercise;
use App\Entity\User;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\ExerciseSetRepository;
use App\Service\OneRepMax\View\OneRepMaxEstimate;
use App\Service\Utils\WeightConverterService;

/**
 * 1RM estimé affiché dans l'historique d'un exercice : le meilleur de toutes ses séries, jamais
 * stocké, recalculé à chaque affichage depuis les séries (en kg en base). Au poids de corps, il
 * porte sur la charge réelle (lest + part du poids de corps figée sur chaque série), comme le
 * record affiché juste au-dessus.
 */
final readonly class ExerciseOneRepMaxEstimator
{
    public function __construct(
        private ExerciseSetRepository $exerciseSetRepository,
        private OneRepMaxCalculator $calculator,
        private WeightConverterService $weightConverter,
    ) {
    }

    public function estimate(User $user, Exercise $exercise): ?OneRepMaxEstimate
    {
        if (! $this->appliesTo($user, $exercise)) {
            return null;
        }

        $rawSets = $this->exerciseSetRepository->findSessionHistoryForExerciseAndUser($user, $exercise);
        $best = $this->bestSet($this->usableSets($rawSets, $exercise));
        if (null === $best) {
            return null;
        }

        return new OneRepMaxEstimate(
            value: $this->weightConverter->convertToLbs($best['oneRepMax'], $user->unitOfMeasure),
            weight: $this->weightConverter->convertToLbs($best['weight'], $user->unitOfMeasure),
            reps: $best['reps'],
            performedAt: $best['performedAt'],
            includesBodyweight: $this->isBodyweight($exercise),
        );
    }

    /**
     * Sans le poids de l'utilisateur, l'historique l'invite à le renseigner plutôt que d'estimer.
     */
    private function appliesTo(User $user, Exercise $exercise): bool
    {
        return MeasurementType::WEIGHT_REPS === $exercise->measurementType
            && (! $this->isBodyweight($exercise) || null !== $user->bodyweightKg);
    }

    private function isBodyweight(Exercise $exercise): bool
    {
        return null !== $exercise->bodyweightPercent;
    }

    /**
     * Une série au poids de corps enregistrée avant que ce poids soit figé sur chaque série n'en
     * porte que le lest : l'estimer sous-évaluerait le 1RM.
     *
     * @param array<int, array{performedAt: \DateTimeImmutable, weight: float, addedWeight: float, reps: int}> $rawSets
     *
     * @return array<int, array{performedAt: \DateTimeImmutable, weight: float, addedWeight: float, reps: int}>
     */
    private function usableSets(array $rawSets, Exercise $exercise): array
    {
        if (! $this->isBodyweight($exercise)) {
            return $rawSets;
        }

        return array_filter($rawSets, static fn (array $set): bool => $set['weight'] > $set['addedWeight']);
    }

    /**
     * À estimation égale, la première série qui l'a atteinte reste la référence (comme un record).
     *
     * @param array<int, array{performedAt: \DateTimeImmutable, weight: float, reps: int}> $rawSets
     *
     * @return array{oneRepMax: float, weight: float, reps: int, performedAt: \DateTimeImmutable}|null
     */
    private function bestSet(array $rawSets): ?array
    {
        $best = null;
        foreach ($rawSets as $set) {
            $oneRepMax = $this->calculator->estimate($set['weight'], $set['reps']);
            if (null !== $oneRepMax && (null === $best || $oneRepMax > $best['oneRepMax'])) {
                $best = [
                    'oneRepMax' => $oneRepMax,
                    'weight' => $set['weight'],
                    'reps' => $set['reps'],
                    'performedAt' => $set['performedAt'],
                ];
            }
        }

        return $best;
    }
}
