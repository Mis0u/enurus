<?php

declare(strict_types=1);

namespace App\Enum\YearInReview;

/**
 * Unité d'un poids affiché dans le résumé annuel — la valeur nomme sa clé de traduction
 * (`year_in_review.weight.<valeur>`).
 */
enum YearInReviewWeightUnitEnum: string
{
    case KILOGRAM = 'kg';
    case TONNE = 't';
    case POUND = 'lb';
}
