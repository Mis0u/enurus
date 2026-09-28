<?php

declare(strict_types=1);

namespace App\Service\Workout;

/**
 * Niveau d'intensité (1 à 3) de chaque jour actif du calendrier heatmap, relatif aux propres
 * séances de l'utilisateur : ses jours sont répartis en tiers selon leur tonnage. Des paliers fixes
 * en kg laisseraient un débutant toujours en clair et un pratiquant confirmé toujours en foncé.
 */
final readonly class HeatmapLevelCalculator
{
    public const int LEVEL_LIGHT = 1;

    public const int LEVEL_MEDIUM = 2;

    public const int LEVEL_INTENSE = 3;

    // En dessous, un découpage en tiers n'a pas de sens : la seule séance apparaîtrait « légère ».
    private const int MIN_DAYS_TO_COMPARE = 3;

    /**
     * @param array<string, float> $tonnageByDay clé `Y-m-d` => tonnage cumulé (kg) des séances du jour
     * @return array<string, int> clé `Y-m-d` => niveau
     */
    public function levelByDay(array $tonnageByDay): array
    {
        $liftedTonnages = array_values(array_filter($tonnageByDay, static fn (float $tonnage): bool => 0.0 < $tonnage));

        return array_map(
            fn (float $tonnage): int => $this->levelFor($tonnage, $liftedTonnages),
            $tonnageByDay,
        );
    }

    /**
     * @param list<float> $liftedTonnages
     */
    private function levelFor(float $tonnage, array $liftedTonnages): int
    {
        // Poids du corps, gainage sans lest, cardio : la séance a eu lieu, elle reste visible.
        if (0.0 >= $tonnage) {
            return self::LEVEL_LIGHT;
        }

        $comparableDayCount = \count($liftedTonnages);

        if (self::MIN_DAYS_TO_COMPARE > $comparableDayCount) {
            return self::LEVEL_MEDIUM;
        }

        $lighterDayCount = \count(array_filter($liftedTonnages, static fn (float $other): bool => $other < $tonnage));

        return self::LEVEL_LIGHT + intdiv(self::LEVEL_INTENSE * $lighterDayCount, $comparableDayCount);
    }
}
