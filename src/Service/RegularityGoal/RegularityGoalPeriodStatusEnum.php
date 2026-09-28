<?php

declare(strict_types=1);

namespace App\Service\RegularityGoal;

/**
 * État d'une période (jour ou semaine) d'un objectif de régularité, tel qu'affiché case par case.
 */
enum RegularityGoalPeriodStatusEnum: string
{
    case MET = 'met';
    case MISSED = 'missed';
    case IN_PROGRESS = 'in_progress';
    case UPCOMING = 'upcoming';
}
