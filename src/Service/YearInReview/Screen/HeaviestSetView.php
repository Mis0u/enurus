<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

/**
 * Série la plus lourde de l'année, poids dans l'unité du lecteur. Nom d'exercice public = clé du
 * domaine de traduction `exercise`.
 */
final readonly class HeaviestSetView
{
    public function __construct(
        public string $exerciseName,
        public bool $isPublicExercise,
        public YearInReviewWeight $weight,
        public \DateTimeImmutable $performedOn,
    ) {
    }
}
