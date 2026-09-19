<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

/**
 * Les quatre périodes sur lesquelles le dashboard calcule ses widgets : la dernière journée
 * d'entraînement, la semaine et le mois courants, et l'année écoulée.
 */
final readonly class DashboardPeriods
{
    public function __construct(
        public DashboardPeriod $day,
        public DashboardPeriod $week,
        public DashboardPeriod $month,
        public DashboardPeriod $year,
    ) {
    }
}
