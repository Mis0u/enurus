<?php

declare(strict_types=1);

namespace App\Twig\Components\Navigation\Sidebar;

use App\Twig\Components\Trait\WithSvgIcon;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class NavigationItem
{
    use WithSvgIcon;

    public string $link = '#';

    public string $menu;

    public string $route = '';

    public bool $badge = false;

    public int $totalNotification = 0;
}
