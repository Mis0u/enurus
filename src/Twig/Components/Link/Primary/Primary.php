<?php

declare(strict_types=1);

namespace App\Twig\Components\Link\Primary;

use App\Twig\Components\Link\AbstractLink;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Primary extends AbstractLink
{
    public function getClasses(): string
    {
        return 'flex items-center gap-[10px] px-3 py-[11px] rounded-[10px] whitespace-nowrap overflow-hidden mb-[10px] bg-gradient-to-br from-rose-ft to-red-600 shadow-[0_4px_15px_rgba(244,63,94,0.3)] transition-ft hover:shadow-[0_6px_22px_rgba(244,63,94,0.5)] hover:-translate-y-px w-full no-underline';
    }
}
