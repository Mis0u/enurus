<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

/**
 * @phpstan-import-type MuscleBars from DashboardMuscleDistributionService
 */
final readonly class DashboardMuscleFilterView
{
    /**
     * @param list<string> $primary   ids SVG des muscles primaires
     * @param list<string> $secondary ids SVG des muscles secondaires
     * @param MuscleBars   $bars
     */
    public function __construct(
        public array $primary,
        public array $secondary,
        public array $bars,
    ) {
    }

    public static function empty(): self
    {
        return new self([], [], [
            'bars' => [],
            'remainingCount' => 0,
        ]);
    }
}
