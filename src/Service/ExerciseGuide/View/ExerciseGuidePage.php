<?php

declare(strict_types=1);

namespace App\Service\ExerciseGuide\View;

use App\Service\ExerciseGuide\ExerciseGuide;

/**
 * Données prêtes à afficher de la fiche publique d'un exercice.
 */
final readonly class ExerciseGuidePage
{
    /**
     * @param list<string>            $primaryMuscleKeys   clés de traduction (domaine `muscle`), dans l'ordre des groupes
     * @param list<string>            $secondaryMuscleKeys
     * @param list<string>            $primarySvgIds       zones de la silhouette coloriées en muscle principal
     * @param list<string>            $secondarySvgIds
     * @param list<ExerciseGuideLink> $variants
     */
    public function __construct(
        public ExerciseGuide $guide,
        public string $exerciseId,
        public string $nameKey,
        public array $primaryMuscleKeys,
        public array $secondaryMuscleKeys,
        public array $primarySvgIds,
        public array $secondarySvgIds,
        public array $variants,
    ) {
    }
}
