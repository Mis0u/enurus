<?php

declare(strict_types=1);

namespace App\Service\ExerciseGuide\View;

/**
 * Groupe musculaire de l'index des fiches et les exercices qui le travaillent en muscle principal.
 */
final readonly class ExerciseGuideMuscleGroup
{
    /**
     * @param string                  $muscleKey clé de traduction (domaine `muscle`)
     * @param list<ExerciseGuideLink> $exercises
     */
    public function __construct(
        public string $muscleKey,
        public array $exercises,
    ) {
    }
}
