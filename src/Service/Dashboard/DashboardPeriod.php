<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

readonly class DashboardPeriod
{
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
    ) {
    }
}
