<?php

declare(strict_types=1);

namespace App\Twig\Components\Flash\Success;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Success
{
    public string $message;
}
