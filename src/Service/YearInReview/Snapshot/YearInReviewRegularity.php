<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Snapshot;

final readonly class YearInReviewRegularity
{
    public const int FIRST_MONTH = 1;

    public const int LAST_MONTH = 12;

    /**
     * @param int<1, 12> $busiestMonth
     */
    public function __construct(
        public int $longestStreakWeeks,
        public int $busiestMonth,
        public int $busiestMonthWorkoutCount,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            SnapshotArrayReader::int($data, 'longestStreakWeeks'),
            self::month(SnapshotArrayReader::int($data, 'busiestMonth')),
            SnapshotArrayReader::int($data, 'busiestMonthWorkoutCount'),
        );
    }

    /**
     * @return array{longestStreakWeeks: int, busiestMonth: int, busiestMonthWorkoutCount: int}
     */
    public function toArray(): array
    {
        return [
            'longestStreakWeeks' => $this->longestStreakWeeks,
            'busiestMonth' => $this->busiestMonth,
            'busiestMonthWorkoutCount' => $this->busiestMonthWorkoutCount,
        ];
    }

    /**
     * @return int<1, 12>
     */
    private static function month(int $month): int
    {
        if (self::FIRST_MONTH > $month || self::LAST_MONTH < $month) {
            throw new \LogicException('Year in review snapshot: "busiestMonth" must be between 1 and 12.');
        }

        return $month;
    }
}
