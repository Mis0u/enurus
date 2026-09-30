<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Snapshot;

use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;

final readonly class YearInReviewBadge
{
    public function __construct(
        public BadgeFamilyEnum $family,
        public BadgeTierEnum $tier,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            BadgeFamilyEnum::from(SnapshotArrayReader::string($data, 'family')),
            BadgeTierEnum::from(SnapshotArrayReader::int($data, 'tier')),
        );
    }

    /**
     * @return array{family: string, tier: int}
     */
    public function toArray(): array
    {
        return [
            'family' => $this->family->value,
            'tier' => $this->tier->value,
        ];
    }
}
