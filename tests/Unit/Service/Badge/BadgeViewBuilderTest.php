<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Badge;

use App\Entity\User;
use App\Entity\UserBadge;
use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Repository\UserBadgeRepository;
use App\Service\Badge\BadgeKey;
use App\Service\Badge\BadgeLabelFormatter;
use App\Service\Badge\BadgeProgress;
use App\Service\Badge\BadgeViewBuilder;
use App\Service\Badge\View\BadgeCollectionView;
use App\Service\Badge\View\BadgeFamilyView;
use PHPUnit\Framework\TestCase;

final class BadgeViewBuilderTest extends TestCase
{
    public function testEveryFamilyListsSixTilesAndTotalCountsTheLegend(): void
    {
        $view = $this->build(new BadgeProgress(0, 0, 0, 0.0), owned: []);

        self::assertCount(4, $view->families);
        foreach ($view->families as $family) {
            self::assertCount(6, $family->tiles);
        }
        self::assertSame(25, $view->totalCount);
        self::assertSame(0, $view->earnedCount);
    }

    public function testOwnedBadgeIsEarnedWithItsDateAndLockedOnesCarryProgress(): void
    {
        $user = new User();
        $owned = [$this->owned($user, BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::BRONZE, '2026-09-01')];

        $view = $this->build(new BadgeProgress(73, 0, 0, 0.0), $owned, $user);
        $assiduity = $this->family($view, BadgeFamilyEnum::ASSIDUITY);

        self::assertTrue($assiduity->tiles[0]->isEarned());
        self::assertEquals(new \DateTimeImmutable('2026-09-01'), $assiduity->tiles[0]->unlockedAt);
        self::assertFalse($assiduity->tiles[3]->isEarned());
        self::assertSame(73, $assiduity->tiles[3]->progressPercent);
        self::assertSame(1, $view->earnedCount);
    }

    public function testLatestIsOrderedFromMostRecentAndCappedToThree(): void
    {
        $user = new User();
        $owned = [
            $this->owned($user, BadgeFamilyEnum::TONNAGE, BadgeTierEnum::SILVER, '2026-09-21'),
            $this->owned($user, BadgeFamilyEnum::REGULARITY, BadgeTierEnum::BRONZE, '2026-09-02'),
            $this->owned($user, BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::BRONZE, '2025-07-03'),
            $this->owned($user, BadgeFamilyEnum::SENIORITY, BadgeTierEnum::BRONZE, '2025-08-01'),
        ];

        $view = $this->build(new BadgeProgress(1, 1, 4, 100_000.0), $owned, $user);

        self::assertCount(3, $view->latest);
        self::assertSame(BadgeFamilyEnum::TONNAGE, $view->latest[0]->family);
        self::assertSame(BadgeFamilyEnum::REGULARITY, $view->latest[1]->family);
        self::assertSame(BadgeFamilyEnum::SENIORITY, $view->latest[2]->family);
    }

    public function testNextListsFirstLockedTierOfEachFamilySortedByProgress(): void
    {
        $user = new User();
        $owned = [$this->owned($user, BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::BRONZE, '2026-09-01')];

        // assiduité 5/10 = 50 %, ancienneté 0/1, régularité 3/4 = 75 %, tonnage 2/10 = 20 %
        $view = $this->build(new BadgeProgress(5, 0, 3, 2000.0), $owned, $user);

        self::assertCount(3, $view->next);
        self::assertSame(BadgeFamilyEnum::REGULARITY, $view->next[0]->family);
        self::assertSame(BadgeFamilyEnum::ASSIDUITY, $view->next[1]->family);
        self::assertSame(BadgeTierEnum::SILVER, $view->next[1]->tier);
        self::assertSame(BadgeFamilyEnum::TONNAGE, $view->next[2]->family);
    }

    public function testCompleteFamilyIsFlaggedAndSkippedFromNext(): void
    {
        $user = new User();
        $owned = array_map(
            fn (BadgeTierEnum $tier): UserBadge => $this->owned($user, BadgeFamilyEnum::SENIORITY, $tier, '2026-01-01'),
            BadgeTierEnum::cases(),
        );

        $view = $this->build(new BadgeProgress(0, 61, 0, 0.0), $owned, $user);

        self::assertTrue($this->family($view, BadgeFamilyEnum::SENIORITY)->isComplete);
        self::assertTrue($view->rubyByFamily[BadgeFamilyEnum::SENIORITY->value]);
        self::assertFalse($view->rubyByFamily[BadgeFamilyEnum::TONNAGE->value]);
        foreach ($view->next as $tile) {
            self::assertNotSame(BadgeFamilyEnum::SENIORITY, $tile->family);
        }
    }

    public function testLegendTileFollowsItsOwnedRow(): void
    {
        $user = new User();
        $owned = [UserBadge::unlock($user, BadgeKey::legend(), new \DateTimeImmutable('2026-09-01'), null)];

        $view = $this->build(new BadgeProgress(0, 0, 0, 0.0), $owned, $user);

        self::assertTrue($view->legend->isEarned());
        self::assertTrue($view->legend->isLegend());
    }

    /**
     * @param list<UserBadge> $owned
     */
    private function build(BadgeProgress $progress, array $owned, ?User $user = null): BadgeCollectionView
    {
        $repository = $this->createStub(UserBadgeRepository::class);
        $repository->method('findByOwner')->willReturn($owned);

        $formatter = $this->createStub(BadgeLabelFormatter::class);
        $formatter->method('name')->willReturn('name');
        $formatter->method('ribbon')->willReturn('ribbon');
        $formatter->method('progress')->willReturn('progress');

        $user ??= new User();

        return (new BadgeViewBuilder($repository, $formatter))->build($user, $user, $progress);
    }

    private function owned(User $user, BadgeFamilyEnum $family, BadgeTierEnum $tier, string $date): UserBadge
    {
        return UserBadge::unlock($user, new BadgeKey($family, $tier), new \DateTimeImmutable($date), null);
    }

    private function family(BadgeCollectionView $view, BadgeFamilyEnum $family): BadgeFamilyView
    {
        foreach ($view->families as $familyView) {
            if ($familyView->family === $family) {
                return $familyView;
            }
        }

        throw new \LogicException('Family not found in view.');
    }
}
