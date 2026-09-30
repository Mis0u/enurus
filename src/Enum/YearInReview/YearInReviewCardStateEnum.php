<?php

declare(strict_types=1);

namespace App\Enum\YearInReview;

/**
 * État d'une année sur la page « Mes résumés » — la valeur nomme le partial Twig de la carte
 * (`year_in_review/list/_card_<valeur>.html.twig`).
 */
enum YearInReviewCardStateEnum: string
{
    /**
     * Résumé figé et éligible : ses écrans sont consultables.
     */
    case READY = 'ready';

    /**
     * Moins de 5 séances dans la période : message d'encouragement.
     */
    case NOT_ENOUGH = 'not_enough';

    /**
     * Année publiée, compte déjà là à la publication, mais la tâche de minuit n'a pas encore figé
     * son résumé (quelques minutes au plus).
     */
    case PENDING = 'pending';
}
