<?php

declare(strict_types=1);

namespace App\Enum\Entity\Workout;

/**
 * Ressenti optionnel d'une séance, saisi en fin de création ou modifiable en édition — chips à
 * icône dans l'UI (cf. `workout--mood-selector`), jamais de sélection multiple : une séance a un
 * ressenti dominant, pas plusieurs à la fois.
 */
enum WorkoutMoodEnum: string
{
    case EN_FORME = 'en_forme';
    case NORMAL = 'normal';
    case FATIGUE = 'fatigue';
    case BLESSE = 'blesse';
    case GROSSE_PERF = 'grosse_perf';
}
