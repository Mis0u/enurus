<?php

declare(strict_types=1);

namespace App\Service\Export;

/**
 * Un texte saisi par l'utilisateur (nom de routine, d'exercice) commençant par `=`, `+`, `-` ou `@`
 * serait exécuté comme une formule à l'ouverture du CSV dans un tableur. Une apostrophe en tête le
 * force en texte (recommandation OWASP « CSV injection »).
 */
final class SpreadsheetSafeText
{
    private const string FORMULA_TRIGGERS = "=+-@\t\r";

    public static function escape(string $text): string
    {
        if ('' === $text || ! str_contains(self::FORMULA_TRIGGERS, $text[0])) {
            return $text;
        }

        return "'" . $text;
    }
}
