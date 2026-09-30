<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Enum\YearInReview\YearInReviewScreenEnum;

/**
 * Un écran du parcours, données prêtes à afficher (langue et unité du lecteur appliquées).
 */
interface YearInReviewScreen
{
    public function type(): YearInReviewScreenEnum;
}
