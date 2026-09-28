<?php

declare(strict_types=1);

namespace App\Service\RegularityGoal;

final readonly class RegularityGoalPeriodProgress
{
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public int $sessionCount,
        public RegularityGoalPeriodStatusEnum $status,
    ) {
    }
}
