<?php

declare(strict_types=1);

namespace App\Enum\Badge;

/**
 * Seule source des paliers de badges. Chaque famille "à paliers" a exactement un seuil par
 * `BadgeTierEnum`, dans l'ordre croissant. `LEGEND` est le méta-badge : aucun seuil propre, il
 * s'obtient en décrochant le rubis des quatre autres familles (`BadgeEligibilityResolver`).
 */
enum BadgeFamilyEnum: string
{
    /** Nombre total de séances. */
    case ASSIDUITY = 'assiduity';

    /** Mois écoulés depuis l'inscription. */
    case SENIORITY = 'seniority';

    /** Plus longue série de semaines consécutives avec au moins une séance. */
    case REGULARITY = 'regularity';

    /** Tonnage cumulé, en tonnes (stocké en kg en base, comme tout poids). */
    case TONNAGE = 'tonnage';

    case LEGEND = 'legend';

    /**
     * @return list<self>
     */
    public static function milestoneFamilies(): array
    {
        return [self::ASSIDUITY, self::SENIORITY, self::REGULARITY, self::TONNAGE];
    }

    /**
     * @return list<int> un seuil par niveau, du bronze au rubis
     */
    public function thresholds(): array
    {
        return match ($this) {
            self::ASSIDUITY => [1, 10, 50, 100, 500, 1000],
            self::SENIORITY => [1, 6, 12, 24, 36, 60],
            self::REGULARITY => [4, 12, 26, 52, 104, 208],
            self::TONNAGE => [10, 100, 500, 1000, 5000, 10000],
            self::LEGEND => [],
        };
    }

    public function thresholdOf(BadgeTierEnum $tier): int
    {
        return $this->thresholds()[$tier->value - 1]
            ?? throw new \LogicException(\sprintf('Badge family "%s" has no threshold.', $this->value));
    }

    /**
     * L'ancienneté ne dépend d'aucune séance : elle ne peut jamais redescendre, un badge obtenu
     * reste donc acquis. Toutes les autres familles suivent l'historique de séances et perdent un
     * badge si une suppression le fait repasser sous le seuil.
     */
    public function isRevocable(): bool
    {
        return self::SENIORITY !== $this;
    }
}
