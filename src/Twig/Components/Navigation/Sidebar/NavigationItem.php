<?php

declare(strict_types=1);

namespace App\Twig\Components\Navigation\Sidebar;

use App\Twig\Components\Trait\WithSvgIcon;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class NavigationItem
{
    use WithSvgIcon;

    public string $link = '#';

    public string $menu;

    public string $route = '';

    public bool $badge = false;

    public int $totalNotification = 0;

    /**
     * Rendu de l'entrée : `desktop` (sidebar), `mobile` (barre du bas) ou `tile` (tuile du panneau
     * « Plus » de la navigation mobile) — cf. `_<format>_format.html.twig`.
     */
    public string $format = 'desktop';
}
