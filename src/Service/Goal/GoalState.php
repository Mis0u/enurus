<?php

declare(strict_types=1);

namespace App\Service\Goal;

final readonly class GoalState
{
    /**
     * @param array<GoalProgress> $current  objectifs non atteints, triés du plus récemment défini au plus ancien
     * @param array<GoalProgress> $achieved objectifs atteints, triés du plus récent au plus ancien
     */
    public function __construct(
        public array $current,
        public array $achieved,
    ) {
    }
}
