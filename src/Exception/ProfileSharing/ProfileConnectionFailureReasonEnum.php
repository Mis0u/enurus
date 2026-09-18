<?php

declare(strict_types=1);

namespace App\Exception\ProfileSharing;

/**
 * Les valeurs servent de suffixe aux clés de traduction des messages d'erreur affichés à
 * l'utilisateur — ne jamais les renommer sans migrer les 8 fichiers de langue.
 */
enum ProfileConnectionFailureReasonEnum: string
{
    case SELF_REQUEST = 'self_request';
    case NOT_SEARCHABLE = 'not_searchable';
    case ALREADY_PENDING = 'already_pending';
    case ALREADY_CONNECTED = 'already_connected';
    case COOLDOWN_ACTIVE = 'cooldown_active';
    case INVALID_TRANSITION = 'invalid_transition';
}
