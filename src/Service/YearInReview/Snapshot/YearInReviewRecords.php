<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Snapshot;

/**
 * `heaviestSet` reste null sans série à charge (que des exercices au temps ou à la distance).
 */
final readonly class YearInReviewRecords
{
    public function __construct(
        public int $count,
        public ?YearInReviewHeaviestSet $heaviestSet,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $heaviestSet = SnapshotArrayReader::nullableArray($data, 'heaviestSet');

        return new self(
            SnapshotArrayReader::int($data, 'count'),
            null === $heaviestSet ? null : YearInReviewHeaviestSet::fromArray($heaviestSet),
        );
    }

    /**
     * @return array{count: int, heaviestSet: array{exerciseName: string, isPublicExercise: bool, weightKg: float, performedOn: string}|null}
     */
    public function toArray(): array
    {
        return [
            'count' => $this->count,
            'heaviestSet' => $this->heaviestSet?->toArray(),
        ];
    }
}
