<?php

declare(strict_types=1);

namespace App\Twig\Components\Button\Basic;

use App\Twig\Components\Button\AbstractButton;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Basic extends AbstractButton
{
    public function getClasses(): string
    {
        return 'flex-1 px-5 py-3 rounded-[10px] text-sm font-semibold cursor-pointer transition-all duration-250 bg-white/[0.06] border border-white/[0.07] text-[#f0f4ff] hover:bg-white/[0.1]';
    }
}
