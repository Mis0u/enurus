<?php

declare(strict_types=1);

namespace App\Enum\Translations;

enum LocaleAllowedEnum: string
{
    case EN = 'en'; // A LAISSER EN 1ÈRE POSITION
    case DE = 'de';
    case ES = 'es';
    case FR = 'fr';
    case IT = 'it';
    case NL = 'nl';
    case PL = 'pl';
    case PT = 'pt';

    /**
     * @return string[]
     */
    public static function getAllowedLocale(): array
    {
        return array_column(self::cases(), 'value');
    }
}
