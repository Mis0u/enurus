<?php

declare(strict_types=1);

namespace App\Twig\Components\Button\Primary;

use App\Twig\Components\Button\AbstractButton;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Primary extends AbstractButton
{
    public function getClasses(): string
    {
        return 'cursor-pointer w-full py-3.5 px-4 bg-gradient-to-r from-rose-500 to-red-600 hover:from-rose-600 hover:to-red-700 text-white font-bold rounded-xl shadow-lg shadow-rose-500/30 hover:shadow-rose-500/50 transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none disabled:shadow-none disabled:hover:scale-100';
    }
}
