<?php

declare(strict_types=1);

namespace App\Enum\Export;

/**
 * Formats d'export des séances (Réglages › Mes données). La valeur est le segment d'URL
 * (`/reglages/export/{format}`) et l'extension du fichier téléchargé.
 */
enum WorkoutExportFormatEnum: string
{
    // Pour un tableur : une ligne par série, dans l'unité et le format de nombre de l'utilisateur.
    case CSV = 'csv';
    // Données brutes et structurées (séance → exercices → séries), poids en kg.
    case JSON = 'json';

    public function contentType(): string
    {
        return match ($this) {
            self::CSV => 'text/csv; charset=UTF-8',
            self::JSON => 'application/json',
        };
    }
}
