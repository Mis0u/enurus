<?php

declare(strict_types=1);

namespace App\Twig\Components\Message\Error;

use App\Twig\Components\Message\AbstractMessage;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Error extends AbstractMessage
{
    public function getClasses(): string
    {
        return 'error-msg bg-red-500/20 border-l-4 border-red-500 rounded-lg p-4 backdrop-blur-sm animate-shake';
    }
}
