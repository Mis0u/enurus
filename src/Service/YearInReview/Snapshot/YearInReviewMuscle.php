<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Snapshot;

/**
 * Id du `MuscleGroup` (référentiel stable, seedé par migration) : son nom traduit et ses `svgIds`
 * sont relus à l'affichage.
 */
final readonly class YearInReviewMuscle
{
    public function __construct(
        public string $muscleGroupId,
        public int $setCount,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(SnapshotArrayReader::string($data, 'muscleGroupId'), SnapshotArrayReader::int($data, 'setCount'));
    }

    /**
     * @return array{muscleGroupId: string, setCount: int}
     */
    public function toArray(): array
    {
        return [
            'muscleGroupId' => $this->muscleGroupId,
            'setCount' => $this->setCount,
        ];
    }
}
