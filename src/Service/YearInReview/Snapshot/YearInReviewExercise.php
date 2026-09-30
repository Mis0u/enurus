<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Snapshot;

/**
 * Nom stocké tel quel (pas l'id) : le résumé reste lisible si l'exercice est supprimé ensuite. Le
 * nom d'un exercice public est une clé du domaine de traduction `exercise`, traduite à l'affichage.
 */
final readonly class YearInReviewExercise
{
    public function __construct(
        public string $name,
        public bool $isPublic,
        public int $workoutCount,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            SnapshotArrayReader::string($data, 'name'),
            SnapshotArrayReader::bool($data, 'isPublic'),
            SnapshotArrayReader::int($data, 'workoutCount'),
        );
    }

    /**
     * @return array{name: string, isPublic: bool, workoutCount: int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'isPublic' => $this->isPublic,
            'workoutCount' => $this->workoutCount,
        ];
    }
}
