<?php

declare(strict_types=1);

namespace App\Enum\Badge;

/**
 * Niveau d'un badge, du bronze (1) au rubis (6) — la valeur est aussi l'index 1-based du seuil
 * dans `BadgeFamilyEnum::thresholds()`. Les méthodes `has*()` décident des ornements du SVG
 * (`templates/partials/_badge/_badge.html.twig`) : chaque niveau ajoute une décoration au
 * précédent, pour que deux paliers voisins ne se ressemblent jamais.
 */
enum BadgeTierEnum: int
{
    case BRONZE = 1;
    case SILVER = 2;
    case GOLD = 3;
    case PLATINUM = 4;
    case EMERALD = 5;
    case RUBY = 6;

    public function hasRibbonTails(): bool
    {
        return $this->value >= self::SILVER->value;
    }

    public function hasRivets(): bool
    {
        return $this->value >= self::SILVER->value;
    }

    public function hasSpeedStreaks(): bool
    {
        return $this->value >= self::GOLD->value;
    }

    public function hasSparkle(): bool
    {
        return $this->value >= self::GOLD->value;
    }

    public function hasLaurels(): bool
    {
        return $this->value >= self::PLATINUM->value;
    }

    public function hasGlow(): bool
    {
        return $this->value >= self::PLATINUM->value;
    }

    public function hasCrown(): bool
    {
        return $this->value >= self::EMERALD->value;
    }

    public function hasDoubleStreaks(): bool
    {
        return self::RUBY === $this;
    }
}
