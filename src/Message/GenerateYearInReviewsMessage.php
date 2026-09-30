<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Déclenche la génération des résumés annuels — dispatché le 16 décembre à minuit (Paris) par
 * App\Scheduler\MaintenanceSchedule. Sans année : le handler prend la dernière année publiée, un
 * message récurrent ne pouvant pas porter une valeur qui change chaque année.
 */
final readonly class GenerateYearInReviewsMessage
{
}
