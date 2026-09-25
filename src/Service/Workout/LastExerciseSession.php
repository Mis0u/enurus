<?php

declare(strict_types=1);

namespace App\Service\Workout;

use DateTimeImmutable;

/**
 * Dernière séance d'un utilisateur sur un exercice : sa date et ses séries brutes, poids en kg
 * comme en base (la conversion d'unité reste à la couche d'affichage).
 */
final readonly class LastExerciseSession
{
    /**
     * @param list<array{weight: float, reps: int, duration: ?int, distance: ?int}> $sets triées par position
     */
    public function __construct(
        public DateTimeImmutable $performedAt,
        public array $sets,
    ) {
    }
}
