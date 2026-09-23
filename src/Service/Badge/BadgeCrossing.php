<?php

declare(strict_types=1);

namespace App\Service\Badge;

/**
 * Moment où un palier a réellement été franchi, et séance responsable (nulle pour l'ancienneté).
 */
final readonly class BadgeCrossing
{
    public function __construct(
        public \DateTimeImmutable $date,
        public ?string $workoutId,
    ) {
    }
}
