<?php

declare(strict_types=1);

namespace App\Service\Goal;

use App\Entity\ExerciseGoal;
use App\Enum\Entity\Exercise\MeasurementType;
use DateTimeImmutable;

/**
 * État d'un objectif, calculé à la volée à partir des séances de l'utilisateur — jamais stocké
 * (voir `GoalProgressResolver`). Si l'utilisateur modifie/supprime la séance qui faisait
 * atteindre la cible, un nouveau calcul retombe naturellement sur `achieved: false`.
 */
final readonly class GoalProgress
{
    private const int PERCENT_MAX = 100;

    public bool $achieved;

    public float $currentValue;

    /**
     * Reps de la meilleure série vers un objectif "X reps à Y kg" (`WEIGHT_REPS` avec
     * `targetReps` renseigné), `null` sinon.
     */
    public ?int $currentReps;

    /**
     * Charge additionnelle (kg) de la meilleure série vers un objectif "X (durée/distance) à Y kg"
     * (`TIME`/`DISTANCE` avec `targetWeight` renseigné), `null` sinon.
     */
    public ?float $currentSecondaryWeight;

    public int $percent;

    public ?DateTimeImmutable $achievedAt;

    /**
     * @param array<int, array{workoutId: string, performedAt: DateTimeImmutable, weight: float, reps: int, duration: ?int, distance: ?int}> $sessionRows
     *        triées chronologiquement (ASC), même format que
     *        `ExerciseSetRepository::findSessionHistoryForExerciseAndUser()`.
     */
    public function __construct(
        public ExerciseGoal $goal,
        array $sessionRows,
    ) {
        $secondaryTarget = self::secondaryTarget($goal);

        $state = null !== $secondaryTarget
            ? self::resolveDualTarget($goal, $sessionRows, $secondaryTarget)
            : self::resolveSingleTarget($goal, $sessionRows);

        $this->currentValue = $state['currentValue'];
        $this->currentReps = $state['currentReps'];
        $this->currentSecondaryWeight = $state['currentSecondaryWeight'];
        $this->achieved = $state['achieved'];
        $this->achievedAt = $state['achievedAt'];
        $this->percent = $state['percent'];
    }

    /**
     * @param array<int, array{performedAt: DateTimeImmutable, weight: float, duration: ?int, distance: ?int}> $sessionRows
     * @return array{currentValue: float, currentReps: ?int, currentSecondaryWeight: ?float, achieved: bool, achievedAt: ?DateTimeImmutable, percent: int}
     */
    private static function resolveSingleTarget(ExerciseGoal $goal, array $sessionRows): array
    {
        $target = $goal->targetValue();
        $runningMax = 0.0;
        $achievedAt = null;

        foreach ($sessionRows as $row) {
            $value = self::metricValue($row, $goal->measurementType);
            $runningMax = max($runningMax, $value);

            if (null === $achievedAt && $runningMax >= $target) {
                $achievedAt = $row['performedAt'];
            }
        }

        return [
            'currentValue' => $runningMax,
            'currentReps' => null,
            'currentSecondaryWeight' => null,
            'achieved' => $runningMax >= $target,
            'achievedAt' => $achievedAt,
            'percent' => self::percentOf($runningMax, $target),
        ];
    }

    /**
     * Objectif à deux dimensions ("X reps à Y kg", "X min à Y kg" ou "X m à Y kg" selon le type de
     * mesure) : une série ne compte que si sa métrique principale ET sa métrique secondaire
     * atteignent la cible simultanément — la meilleure série est celle qui s'en approche le plus
     * (ratio min des deux dimensions), jamais les deux maximums pris indépendamment sur des
     * séries différentes.
     *
     * @param array<int, array{performedAt: DateTimeImmutable, weight: float, reps: int, duration: ?int, distance: ?int}> $sessionRows
     * @return array{currentValue: float, currentReps: ?int, currentSecondaryWeight: ?float, achieved: bool, achievedAt: ?DateTimeImmutable, percent: int}
     */
    private static function resolveDualTarget(ExerciseGoal $goal, array $sessionRows, float $secondaryTarget): array
    {
        $type = $goal->measurementType;
        $primaryTarget = $goal->targetValue();

        $bestPrimary = 0.0;
        $bestSecondary = 0.0;
        $bestRatio = 0.0;
        $achievedAt = null;

        foreach ($sessionRows as $row) {
            $primaryValue = self::metricValue($row, $type);
            $secondaryValue = self::secondaryMetricValue($row, $type);
            $ratio = min($primaryValue / $primaryTarget, $secondaryValue / $secondaryTarget);

            // >= (pas >) : à ratio égal, on retient la série la plus récente — plus pertinent à
            // afficher comme "état actuel" qu'un ancien essai chronologiquement plus ancien.
            if ($ratio >= $bestRatio) {
                $bestRatio = $ratio;
                $bestPrimary = $primaryValue;
                $bestSecondary = $secondaryValue;
            }

            if (null === $achievedAt && $primaryValue >= $primaryTarget && $secondaryValue >= $secondaryTarget) {
                $achievedAt = $row['performedAt'];
            }
        }

        return [
            'currentValue' => $bestPrimary,
            'currentReps' => MeasurementType::WEIGHT_REPS === $type ? (int) $bestSecondary : null,
            'currentSecondaryWeight' => MeasurementType::WEIGHT_REPS === $type ? null : $bestSecondary,
            'achieved' => null !== $achievedAt,
            'achievedAt' => $achievedAt,
            'percent' => min(self::PERCENT_MAX, (int) round($bestRatio * self::PERCENT_MAX)),
        ];
    }

    /**
     * Cible de la métrique secondaire optionnelle selon le type de mesure : reps pour
     * `WEIGHT_REPS` (le poids y est déjà la cible principale), charge additionnelle pour
     * `TIME`/`DISTANCE` (où `targetWeight` n'est jamais la cible principale). `null` si
     * l'utilisateur n'a pas renseigné cette dimension optionnelle — objectif mono-dimension.
     */
    private static function secondaryTarget(ExerciseGoal $goal): ?float
    {
        return match ($goal->measurementType) {
            MeasurementType::WEIGHT_REPS => null !== $goal->targetReps ? (float) $goal->targetReps : null,
            MeasurementType::TIME, MeasurementType::DISTANCE => $goal->targetWeight,
        };
    }

    private static function percentOf(float $value, float $target): int
    {
        return 0 < $target ? min(self::PERCENT_MAX, (int) round($value / $target * self::PERCENT_MAX)) : 0;
    }

    /**
     * @param array{weight: float, duration: ?int, distance: ?int} $row
     */
    private static function metricValue(array $row, MeasurementType $type): float
    {
        return match ($type) {
            MeasurementType::WEIGHT_REPS => $row['weight'],
            MeasurementType::TIME => (float) ($row['duration'] ?? 0),
            MeasurementType::DISTANCE => (float) ($row['distance'] ?? 0),
        };
    }

    /**
     * @param array{weight: float, reps: int} $row
     */
    private static function secondaryMetricValue(array $row, MeasurementType $type): float
    {
        return match ($type) {
            MeasurementType::WEIGHT_REPS => (float) $row['reps'],
            MeasurementType::TIME, MeasurementType::DISTANCE => $row['weight'],
        };
    }
}
