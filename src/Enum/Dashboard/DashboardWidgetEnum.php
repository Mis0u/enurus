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
}
