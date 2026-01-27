<?php

declare(strict_types=1);

namespace App\Twig\Components\Button\Secondary;

use App\Twig\Components\Button\AbstractButton;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class Secondary extends AbstractButton
{
    public function getClasses(): string
    {
        return 'cursor-pointer w-full py-3.5 px-4 bg-gradient-to-r from-cyan-500 to-cyan-600 hover:from-cyan-600 hover:to-cyan-700 text-white font-bold rounded-xl shadow-lg shadow-cyan-500/30 hover:shadow-cyan-500/50 transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-900';
    }
}
