<?php

declare(strict_types=1);

namespace App\Service\OneRepMax\View;

/**
 * Meilleur 1RM estimé d'un exercice et la série dont il vient, dans l'unité de l'utilisateur.
 */
final readonly class OneRepMaxEstimate
{
    public function __construct(
        public float $value,
        public float $weight,
        public int $reps,
        public \DateTimeImmutable $performedAt,
    ) {
    }
}
