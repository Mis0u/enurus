<?php

declare(strict_types=1);

namespace App\Enum\Dashboard;

enum DashboardWidgetEnum: string
{
    case SESSION = 'session';
    case TONNAGE = 'tonnage';
    case MUSCLE_DISTRIBUTION = 'muscle_distribution';
    case REGULARITY = 'regularity';
    case GOALS = 'goals';
    case BADGES = 'badges';
    case HEATMAP = 'heatmap';
    case CONNECTIONS = 'connections';

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
