<?php

declare(strict_types=1);

namespace App\Twig\Components\Trait;

trait WithSvgIcon
{
    public ?string $icon = null;

    public function getSvg(): string
    {
        if (null === $this->icon) {
            throw new \RuntimeException('You must set the icon to use this component.');
        }
        return \sprintf('partials/_svg/_%s.html.twig', $this->icon);
    }
}
