<?php

declare(strict_types=1);

namespace App\Service\Badge\View;

use App\Enum\Badge\BadgeFamilyEnum;

final readonly class BadgeFamilyView
{
    /**
     * @param list<BadgeTileView> $tiles du bronze au rubis
     */
    public function __construct(
        public BadgeFamilyEnum $family,
        public array $tiles,
        public bool $isComplete,
    ) {
    }
}
