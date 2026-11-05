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
 * stocké, recalculé à chaque affichage depuis les séries (en kg en base).
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
        if (! $this->appliesTo($exercise)) {
            return null;
        }

        $best = $this->bestSet($this->exerciseSetRepository->findSessionHistoryForExerciseAndUser($user, $exercise));
        if (null === $best) {
            return null;
        }

        return new OneRepMaxEstimate(
            value: $this->weightConverter->convertToLbs($best['oneRepMax'], $user->unitOfMeasure),
            weight: $this->weightConverter->convertToLbs($best['weight'], $user->unitOfMeasure),
            reps: $best['reps'],
            performedAt: $best['performedAt'],
        );
    }

    /**
     * Au poids de corps, la charge saisie n'est qu'un lest : l'estimer seule serait faux.
     */
    private function appliesTo(Exercise $exercise): bool
    {
        return MeasurementType::WEIGHT_REPS === $exercise->measurementType && null === $exercise->bodyweightPercent;
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
