<?php

declare(strict_types=1);

namespace App\Service\Badge\View;

use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;

/**
 * Un badge tel qu'affiché (widget, section, popup, page de séance) — libellés déjà traduits et
 * convertis dans l'unité de celui qui regarde.
 */
final readonly class BadgeTileView
{
    public function __construct(
        public BadgeFamilyEnum $family,
        public BadgeTierEnum $tier,
        public string $name,
        public string $ribbon,
        public ?\DateTimeImmutable $unlockedAt,
        public ?string $progressLabel = null,
        public int $progressPercent = 0,
    ) {
    }

    public function isEarned(): bool
    {
        return null !== $this->unlockedAt;
    }

    public function isLegend(): bool
    {
        return BadgeFamilyEnum::LEGEND === $this->family;
    }
}
