<?php

declare(strict_types=1);

namespace App\Enum\tailwind_class\form\field;

enum FieldClassEnum: string
{
    case ATTRIBUTE_FIELD_CLASS = 'w-full pl-12 pr-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all backdrop-blur-sm';
    case LABEL_ATTRIBUTE = 'block text-sm font-semibold text-slate-200';
}
