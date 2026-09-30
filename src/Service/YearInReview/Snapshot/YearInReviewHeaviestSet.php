<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Snapshot;

/**
 * Même règle de nom que `YearInReviewExercise` : stocké tel quel, clé de traduction si public.
 */
final readonly class YearInReviewHeaviestSet
{
    private const string DAY_FORMAT = 'Y-m-d';

    public function __construct(
        public string $exerciseName,
        public bool $isPublicExercise,
        public float $weightKg,
        public \DateTimeImmutable $performedOn,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $performedOn = \DateTimeImmutable::createFromFormat('!' . self::DAY_FORMAT, SnapshotArrayReader::string($data, 'performedOn'));

        if (false === $performedOn) {
            throw new \LogicException('Year in review snapshot: "performedOn" must be a Y-m-d date.');
        }

        return new self(
            SnapshotArrayReader::string($data, 'exerciseName'),
            SnapshotArrayReader::bool($data, 'isPublicExercise'),
            SnapshotArrayReader::float($data, 'weightKg'),
            $performedOn,
        );
    }

    /**
     * @return array{exerciseName: string, isPublicExercise: bool, weightKg: float, performedOn: string}
     */
    public function toArray(): array
    {
        return [
            'exerciseName' => $this->exerciseName,
            'isPublicExercise' => $this->isPublicExercise,
            'weightKg' => $this->weightKg,
            'performedOn' => $this->performedOn->format(self::DAY_FORMAT),
        ];
    }
}
