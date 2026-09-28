<?php

declare(strict_types=1);

namespace App\Enum\Entity\RegularityGoal;

/**
 * Rythme d'un objectif de régularité : le quota de séances s'applique à chaque jour, ou à chaque
 * semaine du défi — période de 7 jours à partir de sa date de début, pas une semaine calendaire.
 */
enum RegularityGoalPeriodEnum: string
{
    case DAY = 'day';
    case WEEK = 'week';

    /**
     * Quota maximal proposé : au-delà, l'objectif n'a plus de sens pour un suivi de musculation.
     */
    public function maxSessions(): int
    {
        return match ($this) {
            self::DAY => 3,
            self::WEEK => 14,
        };
    }
}
