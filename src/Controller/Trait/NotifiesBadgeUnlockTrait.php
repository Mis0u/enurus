<?php

declare(strict_types=1);

namespace App\Controller\Trait;

use App\Entity\User;
use App\Entity\UserBadge;
use App\Enum\Badge\BadgeFamilyEnum;
use App\Service\Badge\BadgeLabelFormatter;
use App\Service\Badge\BadgeSyncResult;

/**
 * Même principe que `NotifiesGoalAchievementTrait` : les badges gagnés sont posés dans un flash
 * bag lu par `partials/_popup/_badge_unlocked.html.twig` sur la page de destination. Les libellés
 * sont formatés ici (langue et unité de l'utilisateur) et la Légende est séparée des autres (sa
 * propre popup) : Twig ne fait qu'afficher. Un badge perdu n'est jamais notifié.
 */
trait NotifiesBadgeUnlockTrait
{
    private function notifyBadgeUnlocks(BadgeSyncResult $result, User $user, BadgeLabelFormatter $labelFormatter): void
    {
        if ([] === $result->gained) {
            return;
        }

        $format = static fn (UserBadge $badge): array => [
            'family' => $badge->family,
            'tier' => $badge->tier,
            'name' => $labelFormatter->name($badge->key(), $user),
            'ribbon' => $labelFormatter->ribbon($badge->key(), $user),
        ];
        $isLegend = static fn (UserBadge $badge): bool => BadgeFamilyEnum::LEGEND === $badge->family;

        $legend = array_values(array_filter($result->gained, $isLegend));

        $this->addFlash('badge_unlocked', [
            'retroactive' => $result->isRetroactive,
            'badges' => array_map($format, array_values(array_filter($result->gained, static fn (UserBadge $badge): bool => ! $isLegend($badge)))),
            'legend' => [] !== $legend ? $format($legend[0]) : null,
        ]);
    }
}
