<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Entity\User;
use App\Repository\WorkoutRepository;
use App\Repository\WorkoutStatsRepository;
use App\Repository\WorkoutTonnageRepository;
use App\Service\Workout\DeloadPeriodSetService;
use App\Service\Workout\WeeklyStreakCalculator;
use Symfony\Component\Clock\ClockInterface;

/**
 * Valeurs courantes d'un utilisateur pour chaque famille de badges. Non `final` : stubbé par
 * `BadgeSyncServiceTest` (cf. CLAUDE.md, PHPUnit sous-classe la cible).
 */
readonly class BadgeProgressCalculator
{
    private const int MONTHS_PER_YEAR = 12;

    public function __construct(
        private WorkoutRepository $workoutRepository,
        private WorkoutStatsRepository $workoutStatsRepository,
        private WorkoutTonnageRepository $workoutTonnageRepository,
        private DeloadPeriodSetService $deloadPeriodSetService,
        private WeeklyStreakCalculator $weeklyStreakCalculator,
        private ClockInterface $clock,
    ) {
    }

    public function calculate(User $user): BadgeProgress
    {
        $now = $this->clock->now();

        return new BadgeProgress(
            workoutCount: $this->workoutRepository->countByUser($user),
            seniorityMonths: $this->monthsSince($user->createdAt, $now),
            bestRegularityStreakWeeks: $this->weeklyStreakCalculator->longestStreak(
                $this->workoutStatsRepository->findAllPerformedDatesByUser($user),
                $this->deloadPeriodSetService->weekKeySet($user),
                $now,
            ),
            totalTonnageKg: $this->workoutTonnageRepository->sumTotalByUser($user),
        );
    }

    private function monthsSince(\DateTimeImmutable $since, \DateTimeImmutable $now): int
    {
        $interval = $since->diff($now);

        return 1 === $interval->invert ? 0 : $interval->y * self::MONTHS_PER_YEAR + $interval->m;
    }
}
