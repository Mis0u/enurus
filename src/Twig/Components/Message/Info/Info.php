<?php

declare(strict_types=1);

namespace App\Twig\Components\Message\Info;

use App\Twig\Components\Message\AbstractMessage;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Info extends AbstractMessage
{
    public function getClasses(): string
    {
        return 'bg-cyan-500/10 border border-cyan-500/30 rounded-lg p-4 mb-6 backdrop-blur-sm';
    }
}
