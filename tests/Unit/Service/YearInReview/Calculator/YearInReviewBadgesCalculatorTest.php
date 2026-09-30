<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview\Calculator;

use App\Entity\User;
use App\Entity\UserBadge;
use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Repository\UserBadgeRepository;
use App\Service\Badge\BadgeKey;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\YearInReview\Calculator\YearInReviewBadgesCalculator;
use App\Service\YearInReview\Snapshot\YearInReviewBadge;
use PHPUnit\Framework\TestCase;

final class YearInReviewBadgesCalculatorTest extends TestCase
{
    public function testKeepsBadgesUnlockedDuringThePeriodInUnlockOrder(): void
    {
        $user = new User();
        $badgeRepository = $this->createStub(UserBadgeRepository::class);
        $badgeRepository->method('findByOwner')->willReturn([
            $this->badge($user, BadgeFamilyEnum::TONNAGE, BadgeTierEnum::SILVER, '2026-09-01 10:00:00'),
            $this->badge($user, BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::BRONZE, '2025-11-20 10:00:00'),
            $this->badge($user, BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::SILVER, '2026-02-10 10:00:00'),
            $this->badge($user, BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::GOLD, '2026-12-16 09:00:00'),
        ]);

        $badges = new YearInReviewBadgesCalculator($badgeRepository)->calculate($user, $this->period());

        self::assertEquals([
            new YearInReviewBadge(BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::SILVER),
            new YearInReviewBadge(BadgeFamilyEnum::TONNAGE, BadgeTierEnum::SILVER),
        ], $badges);
    }

    private function badge(User $user, BadgeFamilyEnum $family, BadgeTierEnum $tier, string $unlockedAt): UserBadge
    {
        return UserBadge::unlock($user, new BadgeKey($family, $tier), new \DateTimeImmutable($unlockedAt), null);
    }

    private function period(): DashboardPeriod
    {
        return new DashboardPeriod(new \DateTimeImmutable('2026-01-01 00:00:00'), new \DateTimeImmutable('2026-12-15 23:59:59'));
    }
}
