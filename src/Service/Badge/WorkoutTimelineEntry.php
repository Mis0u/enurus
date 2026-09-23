<?php

declare(strict_types=1);

namespace App\Service\Badge;

/**
 * Une séance réduite à ce qu'il faut pour dater le franchissement d'un palier.
 */
final readonly class WorkoutTimelineEntry
{
    public function __construct(
        public string $workoutId,
        public \DateTimeImmutable $performedAt,
        public float $tonnageKg,
    ) {
    }
}
