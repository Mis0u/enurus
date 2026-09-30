<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview\Screen;

use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Service\Badge\View\BadgeTileView;
use App\Service\YearInReview\Screen\BadgesScreen;
use PHPUnit\Framework\TestCase;

final class BadgesScreenTest extends TestCase
{
    public function testAllBadgesFitWhenThereAreNineOrLess(): void
    {
        $screen = new BadgesScreen($this->badges(9));

        self::assertCount(9, $screen->visibleBadges());
        self::assertSame(0, $screen->hiddenBadgeCount());
    }

    public function testBeyondNineTheRestIsCountedInsteadOfShown(): void
    {
        $screen = new BadgesScreen($this->badges(12));

        self::assertCount(9, $screen->visibleBadges());
        self::assertSame(3, $screen->hiddenBadgeCount());
    }

    /**
     * @return list<BadgeTileView>
     */
    private function badges(int $count): array
    {
        return array_fill(0, $count, new BadgeTileView(BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::BRONZE, '1 séance', '1', new \DateTimeImmutable('2026-01-05')));
    }
}
