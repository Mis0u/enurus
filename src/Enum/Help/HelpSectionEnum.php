<?php

declare(strict_types=1);

namespace App\Enum\Help;

/**
 * Sections de la page Aide, dans leur ordre d'affichage. La valeur sert d'ancre (`/aide#library`) :
 * d'autres écrans (tour guidé, messages) peuvent renvoyer vers une section précise — ne jamais la
 * renommer sans chercher ses usages. Le texte vit dans le domaine de traduction `help`
 * (`section.<valeur>.title|intro|points.<point>`).
 */
enum HelpSectionEnum: string
{
    case GETTING_STARTED = 'getting_started';
    case WORKOUT_LOG = 'workout_log';
    case WORKOUTS = 'workouts';
    case LIBRARY = 'library';
    case ROUTINES = 'routines';
    case DASHBOARD = 'dashboard';
    case BADGES = 'badges';
    case CONNECTIONS = 'connections';
    case MESSAGING = 'messaging';
    case SETTINGS = 'settings';

    /**
     * @return list<string> clés des points de la section, dans l'ordre d'affichage
     */
    public function points(): array
    {
        return match ($this) {
            self::GETTING_STARTED => ['log', 'fill', 'come_back'],
            self::WORKOUT_LOG => ['date', 'routine', 'exercises', 'sets', 'prefill', 'details', 'draft', 'missing_exercise', 'records'],
            self::WORKOUTS => ['list', 'detail', 'calendar', 'regularity_goal', 'deload'],
            self::LIBRARY => ['browse', 'create', 'bodyweight', 'history', 'goals', 'archive'],
            self::ROUTINES => ['what', 'use', 'order'],
            self::DASHBOARD => ['progressive', 'blocks', 'customize'],
            self::BADGES => ['families', 'legend', 'where'],
            self::CONNECTIONS => ['principle', 'share', 'request', 'control'],
            self::MESSAGING => ['contact', 'answer'],
            self::SETTINGS => ['profile', 'unit', 'bodyweight', 'export', 'account'],
        };
    }

    /**
     * Page décrite par la section, ouverte par son bouton « Ouvrir ».
     */
    public function route(): string
    {
        return match ($this) {
            self::GETTING_STARTED, self::WORKOUT_LOG => 'app_workout',
            self::WORKOUTS => 'app_workout_list',
            self::LIBRARY => 'app_exercise_list',
            self::ROUTINES => 'app_routine_list',
            self::DASHBOARD => 'app_dashboard',
            self::BADGES => 'app_badge_list',
            self::CONNECTIONS => 'app_profile_connection_list',
            self::MESSAGING => 'app_contact',
            self::SETTINGS => 'app_settings',
        };
    }

    /**
     * Partial de `templates/partials/_svg/` (sans le `_` ni l'extension).
     */
    public function icon(): string
    {
        return match ($this) {
            self::GETTING_STARTED => 'lightning',
            self::WORKOUT_LOG => 'add',
            self::WORKOUTS => 'calendar',
            self::LIBRARY => 'library',
            self::ROUTINES => 'repeat',
            self::DASHBOARD => 'house',
            self::BADGES => 'medal',
            self::CONNECTIONS => 'add_people',
            self::MESSAGING => 'messaging',
            self::SETTINGS => 'gear',
        };
    }
}
