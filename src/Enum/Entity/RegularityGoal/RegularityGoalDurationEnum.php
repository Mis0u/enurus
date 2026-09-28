<?php

declare(strict_types=1);

namespace App\Enum\Entity\RegularityGoal;

/**
 * Durées proposées pour un objectif de régularité, en semaines entières — jamais en mois : un
 * « mois » de 4 semaines (28 jours) annoncerait une date de fin fausse, et un vrai mois calendaire
 * laisserait une dernière semaine incomplète, au quota hebdomadaire injuste. La valeur est le nombre
 * de semaines, affiché tel quel (`regularity_goal.duration`).
 */
enum RegularityGoalDurationEnum: int
{
    case ONE_WEEK = 1;
    case TWO_WEEKS = 2;
    case THREE_WEEKS = 3;
    case FOUR_WEEKS = 4;
    case EIGHT_WEEKS = 8;
    case TWELVE_WEEKS = 12;
}
