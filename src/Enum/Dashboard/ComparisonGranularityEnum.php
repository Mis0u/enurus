<?php

declare(strict_types=1);

namespace App\Enum\Dashboard;

/**
 * Onglets du widget Comparaison : période en cours (du début à aujourd'hui) contre les mêmes jours
 * de la période précédente. La valeur sert de clé d'onglet et de traduction.
 */
enum ComparisonGranularityEnum: string
{
    case WEEK = 'week';
    case MONTH = 'month';
    case YEAR = 'year';
}
