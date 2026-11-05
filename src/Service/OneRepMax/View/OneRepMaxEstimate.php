<?php

declare(strict_types=1);

namespace App\Service\OneRepMax\View;

/**
 * Meilleur 1RM estimé d'un exercice et la série dont il vient, dans l'unité de l'utilisateur.
 * `includesBodyweight` : charge réelle d'un exercice au poids de corps (lest + part du poids).
 */
final readonly class OneRepMaxEstimate
{
    public function __construct(
        public float $value,
        public float $weight,
        public int $reps,
        public \DateTimeImmutable $performedAt,
        public bool $includesBodyweight,
    ) {
    }
}
