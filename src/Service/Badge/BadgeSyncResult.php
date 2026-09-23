<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Entity\UserBadge;

final readonly class BadgeSyncResult
{
    /**
     * @param list<UserBadge> $gained       badges obtenus lors de cette synchro
     * @param bool            $isRetroactive première synchro d'un compte qui avait déjà de quoi
     *                                      débloquer plusieurs badges (déploiement de la feature) :
     *                                      une seule popup récapitulative, pas une par badge
     * @param BadgeProgress   $progress     valeurs courantes, réutilisées pour l'affichage sans
     *                                      recalcul
     */
    public function __construct(
        public array $gained,
        public bool $isRetroactive,
        public BadgeProgress $progress,
    ) {
    }
}
