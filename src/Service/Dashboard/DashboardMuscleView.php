<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

final readonly class DashboardMuscleView
{
    public function __construct(
        public DashboardMuscleFilterView $session,
        public DashboardMuscleFilterView $week,
        public DashboardMuscleFilterView $month,
    ) {
    }
}
