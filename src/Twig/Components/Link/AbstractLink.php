<?php

declare(strict_types=1);

namespace App\Twig\Components\Link;

use App\Twig\Components\Trait\WithSvgIcon;

abstract class AbstractLink
{
    use WithSvgIcon;

    public string $message;

    public string $link = '#';

    public ?string $data = null;

    abstract public function getClasses(): string;
}
