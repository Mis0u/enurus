<?php

declare(strict_types=1);

namespace App\Twig\Components\Message;

use App\Twig\Components\Trait\WithSvgIcon;

abstract class AbstractMessage
{
    use WithSvgIcon;

    public string $message;

    public ?string $variable = null;

    abstract public function getClasses(): string;
}
