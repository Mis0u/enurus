<?php

declare(strict_types=1);

namespace App\Enum\Onboarding;

/**
 * Étapes du tour guidé du dashboard, dans leur ordre d'affichage. La valeur est à la fois la clé de
 * traduction (`tour.step.<valeur>.title|description`, domaine `help`) et la cible dans la page
 * (`data-tour-step="<valeur>"`, posé sur la version desktop ET mobile de l'élément — le controller
 * Stimulus retient celle qui est visible). Sans cible visible (bienvenue, dashboard déjà rempli),
 * la bulle s'affiche centrée.
 */
enum GuidedTourStepEnum: string
{
    case WELCOME = 'welcome';
    case NEW_WORKOUT = 'new_workout';
    case WORKOUTS = 'workouts';
    case LIBRARY = 'library';
    case DASHBOARD = 'dashboard';
    case HELP = 'help';
}
