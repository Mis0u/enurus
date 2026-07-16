<?php

declare(strict_types=1);

namespace App\Twig\Components\Button\Dotted;

use App\Twig\Components\Button\AbstractButton;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Dotted extends AbstractButton
{
    public function getClasses(): string
    {
        return 'flex items-center justify-center gap-2.5 px-4 py-4 bg-white/[0.03] border-2 border-dashed border-white/[0.07] rounded-[14px] text-[#8b9bb4] text-sm font-semibold cursor-pointer transition-all duration-250 mt-2.5 hover:bg-white/[0.06] hover:border-white/[0.15] hover:text-[#f0f4ff]';
    }
}
