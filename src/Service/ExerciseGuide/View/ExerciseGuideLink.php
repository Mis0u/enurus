<?php

declare(strict_types=1);

namespace App\Service\ExerciseGuide\View;

/**
 * Exercice cité sur une page publique : lien vers sa fiche quand il en a une (`slug`), simple nom sinon.
 */
final readonly class ExerciseGuideLink
{
    /**
     * @param string      $nameKey   clé de traduction du nom (domaine `exercise`)
     * @param string|null $reasonKey pourquoi le choisir, pour une variante (domaine `exercise_guide`)
     */
    public function __construct(
        public string $nameKey,
        public ?string $slug,
        public ?string $reasonKey = null,
    ) {
    }
}
