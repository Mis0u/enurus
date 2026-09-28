<?php

declare(strict_types=1);

namespace App\Enum\Dashboard;

/**
 * Widgets du dashboard, dans leur ordre d'affichage par défaut — l'utilisateur peut le changer en
 * réglages (`User::$widgetOrder`, résolu par `inOrder()`).
 */
enum DashboardWidgetEnum: string
{
    case SESSION = 'session';
    case TONNAGE = 'tonnage';
    case MUSCLE_DISTRIBUTION = 'muscle_distribution';
    case REGULARITY = 'regularity';
    case REGULARITY_GOAL = 'regularity_goal';
    case GOALS = 'goals';
    case HEATMAP = 'heatmap';
    case CONNECTIONS = 'connections';
    case BADGES = 'badges';

    /**
     * Ordre d'affichage d'un utilisateur : d'abord les widgets de son ordre enregistré, puis ceux qui
     * n'y figurent pas (débloqués après sa réorganisation, ajoutés depuis), dans l'ordre par défaut.
     * Les clés inconnues (widget supprimé) sont ignorées.
     *
     * @param array<int, string> $savedOrder
     * @return list<self>
     */
    public static function inOrder(array $savedOrder): array
    {
        $savedWidgets = array_values(array_unique(array_filter(
            array_map(self::tryFrom(...), $savedOrder),
        ), SORT_REGULAR));

        return [
            ...$savedWidgets,
            ...array_values(array_filter(self::cases(), static fn (self $widget): bool => ! \in_array($widget, $savedWidgets, true))),
        ];
    }

    /**
     * Un widget personnel n'apparaît jamais sur son dashboard vu par une connexion, ni parmi les
     * widgets qu'on peut lui partager : la liste des connexions exposerait des tiers qui n'ont
     * rien accepté avec celui qui regarde.
     */
    public function isShareable(): bool
    {
        return self::CONNECTIONS !== $this;
    }
}
