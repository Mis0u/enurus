<?php

declare(strict_types=1);

namespace App\Enum\YearInReview;

/**
 * Échelle des équivalences ludiques de l'écran Tonnage du résumé annuel, du plus léger au plus
 * lourd. Règle validée : le plus gros objet soulevé **au moins une fois**, nombre arrondi à
 * l'entier inférieur (« c'est plus que le poids de… » reste toujours vrai). Poids vérifiés ; tour
 * Eiffel et Titanic écartés, inatteignables en un an.
 */
enum TonnageEquivalenceEnum: string
{
    case GRAND_PIANO = 'grand_piano';
    case AFRICAN_ELEPHANT = 'african_elephant';
    case BIG_BEN_BELL = 'big_ben_bell';
    case BOEING_737 = 'boeing_737';
    case AIRBUS_A380 = 'airbus_a380';

    public static function largestLiftedBy(float $tonnageKg): ?self
    {
        $largest = null;

        foreach (self::cases() as $object) {
            if ($object->weightKg() <= $tonnageKg) {
                $largest = $object;
            }
        }

        return $largest;
    }

    /**
     * Poids de référence en kg : piano de concert Steinway D, éléphant d'Afrique mâle, cloche de
     * Big Ben (Great Bell), Boeing 737-800 et Airbus A380 à vide.
     */
    public function weightKg(): float
    {
        return match ($this) {
            self::GRAND_PIANO => 480.0,
            self::AFRICAN_ELEPHANT => 6000.0,
            self::BIG_BEN_BELL => 13700.0,
            self::BOEING_737 => 41100.0,
            self::AIRBUS_A380 => 277000.0,
        };
    }

    public function timesIn(float $tonnageKg): int
    {
        return (int) floor($tonnageKg / $this->weightKg());
    }
}
