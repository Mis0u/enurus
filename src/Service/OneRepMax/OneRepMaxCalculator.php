<?php

declare(strict_types=1);

namespace App\Service\OneRepMax;

/**
 * 1RM (charge maximale sur une répétition) estimé par la formule d'Epley. Même formule dans le
 * navigateur (assets/utils/one_rep_max.js) : les deux sont vérifiées sur les mêmes cas,
 * tests/shared/one_rep_max_cases.json.
 */
final readonly class OneRepMaxCalculator
{
    /**
     * Au-delà, l'endurance fausse trop l'estimation pour la donner.
     */
    public const int MAX_REPS = 12;

    private const int EPLEY_DIVISOR = 30;

    /**
     * Une seule répétition est déjà un 1RM : Epley le gonflerait de 1/30.
     *
     * @return float|null null hors de 1 à MAX_REPS répétitions, ou sans charge
     */
    public function estimate(float $weight, int $reps): ?float
    {
        if (0 >= $weight || 1 > $reps || self::MAX_REPS < $reps) {
            return null;
        }

        return 1 === $reps ? $weight : $weight * (1 + $reps / self::EPLEY_DIVISOR);
    }
}
