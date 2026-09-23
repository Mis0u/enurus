<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Enum\Badge\BadgeFamilyEnum;

/**
 * Valeurs courantes d'un utilisateur pour chaque famille de badges, calculées par
 * `BadgeProgressCalculator` — même unité que `BadgeFamilyEnum::thresholds()` via `valueFor()`.
 */
final readonly class BadgeProgress
{
    private const float KG_PER_TONNE = 1000.0;

    public function __construct(
        public int $workoutCount,
        public int $seniorityMonths,
        public int $bestRegularityStreakWeeks,
        public float $totalTonnageKg,
    ) {
    }

    public function valueFor(BadgeFamilyEnum $family): float
    {
        return match ($family) {
            BadgeFamilyEnum::ASSIDUITY => $this->workoutCount,
            BadgeFamilyEnum::SENIORITY => $this->seniorityMonths,
            BadgeFamilyEnum::REGULARITY => $this->bestRegularityStreakWeeks,
            BadgeFamilyEnum::TONNAGE => $this->totalTonnageKg / self::KG_PER_TONNE,
            BadgeFamilyEnum::LEGEND => throw new \LogicException('The legend badge has no progress value.'),
        };
    }
}
