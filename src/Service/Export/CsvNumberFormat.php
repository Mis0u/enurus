<?php

declare(strict_types=1);

namespace App\Service\Export;

/**
 * Séparateurs d'un CSV selon la langue de l'utilisateur, pour qu'Excel l'ouvre directement en
 * colonnes : l'anglais utilise « , » entre colonnes et le point décimal, les autres langues du site
 * (fr, it, es, pt, de, nl, pl) la virgule décimale, donc « ; » entre colonnes.
 */
final readonly class CsvNumberFormat
{
    public string $columnSeparator;

    private string $decimalSeparator;

    public function __construct(string $locale)
    {
        $usesDecimalPoint = str_starts_with($locale, 'en');
        $this->columnSeparator = $usesDecimalPoint ? ',' : ';';
        $this->decimalSeparator = $usesDecimalPoint ? '.' : ',';
    }

    /**
     * Sans zéros inutiles : 80 plutôt que 80,00, 82,5 plutôt que 82,50.
     */
    public function decimal(float $value, int $decimals): string
    {
        $formatted = number_format($value, $decimals, $this->decimalSeparator, '');

        // Sans partie décimale, les zéros finaux sont ceux de la partie entière (100) : on n'y touche pas.
        if (0 >= $decimals) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), $this->decimalSeparator);
    }
}
