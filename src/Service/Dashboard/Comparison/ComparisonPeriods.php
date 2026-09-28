<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Comparison;

use App\Service\Dashboard\DashboardPeriod;

final readonly class ComparisonPeriods
{
    public function __construct(
        public DashboardPeriod $current,
        public DashboardPeriod $previous,
    ) {
    }
}
