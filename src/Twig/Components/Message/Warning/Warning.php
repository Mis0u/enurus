<?php

declare(strict_types=1);

namespace App\Twig\Components\Message\Warning;

use App\Twig\Components\Message\AbstractMessage;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Warning extends AbstractMessage
{
    public function getClasses(): string
    {
        return 'bg-amber-500/10 border border-amber-500/30 rounded-xl p-4 mb-6 backdrop-blur-sm';
    }
}
