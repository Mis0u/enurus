<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Snapshot;

/**
 * Écrans « Séances », « Tonnage » et « Répétitions ». Tonnage en kg, comme en base : la conversion
 * en lbs et l'équivalence ludique se font à l'affichage.
 */
final readonly class YearInReviewTotals
{
    public function __construct(
        public int $workoutCount,
        public int $setCount,
        public int $repCount,
        public float $tonnageKg,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            SnapshotArrayReader::int($data, 'workoutCount'),
            SnapshotArrayReader::int($data, 'setCount'),
            SnapshotArrayReader::int($data, 'repCount'),
            SnapshotArrayReader::float($data, 'tonnageKg'),
        );
    }

    /**
     * @return array{workoutCount: int, setCount: int, repCount: int, tonnageKg: float}
     */
    public function toArray(): array
    {
        return [
            'workoutCount' => $this->workoutCount,
            'setCount' => $this->setCount,
            'repCount' => $this->repCount,
            'tonnageKg' => $this->tonnageKg,
        ];
    }
}
