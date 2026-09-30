<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Entity\UserBadge;
use App\Repository\UserBadgeRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\YearInReview\Snapshot\YearInReviewBadge;

final readonly class YearInReviewBadgesCalculator
{
    public function __construct(
        private UserBadgeRepository $userBadgeRepository,
    ) {
    }

    /**
     * @return list<YearInReviewBadge> dans l'ordre de déblocage
     */
    public function calculate(User $user, DashboardPeriod $period): array
    {
        $badges = array_filter(
            $this->userBadgeRepository->findByOwner($user),
            static fn (UserBadge $badge): bool => $period->start <= $badge->unlockedAt && $badge->unlockedAt <= $period->end,
        );
        usort($badges, static fn (UserBadge $first, UserBadge $second): int => $first->unlockedAt <=> $second->unlockedAt);

        return array_map(static fn (UserBadge $badge): YearInReviewBadge => new YearInReviewBadge($badge->family, $badge->tier), $badges);
    }
}
