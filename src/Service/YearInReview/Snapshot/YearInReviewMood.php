<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Snapshot;

use App\Enum\Entity\Workout\WorkoutMoodEnum;

/**
 * Humeur la plus fréquente, en % des séances où une humeur a été saisie (pas de toutes les séances).
 */
final readonly class YearInReviewMood
{
    public function __construct(
        public WorkoutMoodEnum $mood,
        public int $percent,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            WorkoutMoodEnum::from(SnapshotArrayReader::string($data, 'mood')),
            SnapshotArrayReader::int($data, 'percent'),
        );
    }

    /**
     * @return array{mood: string, percent: int}
     */
    public function toArray(): array
    {
        return [
            'mood' => $this->mood->value,
            'percent' => $this->percent,
        ];
    }
}
