<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Entity\ProfileConnection;
use App\Entity\User;

/**
 * Une connexion vue du côté d'un utilisateur : `$counterpart` est l'autre partie, déjà résolue pour
 * que la vue n'ait pas à savoir de quel côté se trouve celui qui regarde.
 */
final readonly class ProfileConnectionEntry
{
    public function __construct(
        public ProfileConnection $connection,
        public User $counterpart,
    ) {
    }
}
