<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;

/**
 * Identité d'un badge (famille + niveau), indépendante de son éventuelle ligne `UserBadge` —
 * sert à comparer badges mérités et badges déjà enregistrés.
 */
final readonly class BadgeKey
{
    public function __construct(
        public BadgeFamilyEnum $family,
        public BadgeTierEnum $tier,
    ) {
    }

    public static function legend(): self
    {
        return new self(BadgeFamilyEnum::LEGEND, BadgeTierEnum::RUBY);
    }

    public function id(): string
    {
        return $this->family->value . ':' . $this->tier->value;
    }
}
