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

    /**
     * Pays dont le drapeau représente la langue (fichier assets/images/flags/<code>.svg) : même choix
     * que les réglages, l'anglais sous le drapeau britannique.
     */
    public function flagCountryCode(): string
    {
        return match ($this) {
            self::EN => 'gb',
            default => $this->value,
        };
    }

    /**
     * Valeur `og:locale` (langue_PAYS) des aperçus de lien, sur le même pays que le drapeau.
     */
    public function ogLocale(): string
    {
        return $this->value . '_' . strtoupper($this->flagCountryCode());
    }
}
