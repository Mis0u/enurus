<?php

declare(strict_types=1);

namespace App\Enum\Contact;

/**
 * Ne pilote aucune logique métier (l'application de la restriction repose uniquement sur
 * `User::$contactRestrictedUntil`/`$contactRestrictedPermanently`) — sert uniquement à afficher
 * la durée d'origine dans le message d'état restreint, qu'on ne peut pas déduire fiablement de la
 * seule date de fin une fois le temps écoulé.
 */
enum ContactRestrictionDurationEnum: string
{
    case ONE_WEEK = 'one_week';
    case ONE_MONTH = 'one_month';
}
