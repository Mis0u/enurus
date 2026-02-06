<?php

declare(strict_types=1);

namespace App\Twig\Components\Flash\Message;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Message
{
    public string $message;

    public string $type;
}
