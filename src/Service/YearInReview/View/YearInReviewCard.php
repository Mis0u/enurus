<?php

declare(strict_types=1);

namespace App\Service\YearInReview\View;

use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\YearInReview\YearInReviewCardStateEnum;

/**
 * Une année de la page « Mes résumés », prête à afficher. `tonnage` est déjà dans l'unité du
 * lecteur : en tonnes arrondies pour KG, en livres arrondies pour LBS. `isUnseen` : résumé
 * éligible jamais ouvert (pastille « Nouveau », même règle que le point bleu de la navigation).
 */
final readonly class YearInReviewCard
{
    public function __construct(
        public int $year,
        public YearInReviewCardStateEnum $state,
        public int $workoutCount,
        public int $missingWorkoutCount,
        public int $tonnage,
        public UnitOfMeasureEnum $unit,
        public bool $isUnseen = false,
    ) {
    }
}
