<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;

/**
 * Règle unique "quels badges cet utilisateur mérite-t-il aujourd'hui", à partir de ses valeurs
 * courantes. Pure fonction : aucune requête, aucun état — `BadgeSyncService` compare ensuite ce
 * résultat aux badges déjà enregistrés.
 */
final readonly class BadgeEligibilityResolver
{
    /**
     * @return array<string, BadgeKey> clé = `BadgeKey::id()`
     */
    public function resolve(BadgeProgress $progress): array
    {
        $earned = [];
        $rubyCount = 0;

        foreach (BadgeFamilyEnum::milestoneFamilies() as $family) {
            foreach ($this->earnedTiers($family, $progress->valueFor($family)) as $tier) {
                $key = new BadgeKey($family, $tier);
                $earned[$key->id()] = $key;
                $rubyCount += BadgeTierEnum::RUBY === $tier ? 1 : 0;
            }
        }

        if (\count(BadgeFamilyEnum::milestoneFamilies()) === $rubyCount) {
            $legend = BadgeKey::legend();
            $earned[$legend->id()] = $legend;
        }

        return $earned;
    }

    /**
     * @return list<BadgeTierEnum>
     */
    private function earnedTiers(BadgeFamilyEnum $family, float $value): array
    {
        return array_values(array_filter(
            BadgeTierEnum::cases(),
            static fn (BadgeTierEnum $tier): bool => $value >= $family->thresholdOf($tier),
        ));
    }
}
