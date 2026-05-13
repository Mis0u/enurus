<?php

declare(strict_types=1);

namespace App\Twig\Components\Button;

use App\Twig\Components\Trait\WithSvgIcon;

abstract class AbstractButton
{
    use WithSvgIcon;

    public string $message;

    public string $type = 'submit';

    public bool $disabled = false;

    public ?string $data = null;

    public ?string $class = null;

    abstract public function getClasses(): string;
}
