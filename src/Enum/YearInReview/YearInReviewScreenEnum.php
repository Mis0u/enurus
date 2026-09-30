<?php

declare(strict_types=1);

namespace App\Enum\YearInReview;

/**
 * Écrans du parcours « Ton année », dans leur ordre d'affichage — la valeur nomme le partial Twig
 * de l'écran (`year_in_review/show/_screen_<valeur>.html.twig`).
 */
enum YearInReviewScreenEnum: string
{
    case INTRO = 'intro';
    case SESSIONS = 'sessions';
    case TONNAGE = 'tonnage';
    case REPS = 'reps';
    case TOP_EXERCISES = 'top_exercises';
    case TOP_MUSCLE = 'top_muscle';
    case RECORDS = 'records';
    case REGULARITY = 'regularity';
    case HEATMAP = 'heatmap';
    case BADGES = 'badges';
    case MOOD = 'mood';
    case RECAP = 'recap';
    case THANKS = 'thanks';
}
