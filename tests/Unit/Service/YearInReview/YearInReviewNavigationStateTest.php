<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview;

use App\Entity\User;
use App\Repository\YearInReviewRepository;
use App\Service\YearInReview\YearInReviewCalendar;
use App\Service\YearInReview\YearInReviewNavigationState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class YearInReviewNavigationStateTest extends TestCase
{
    private const string BEFORE_FIRST_PUBLICATION = '2026-12-15 23:59:59 Europe/Paris';

    private const string AFTER_FIRST_PUBLICATION = '2026-12-16 00:00:00 Europe/Paris';

    public function testLinkIsHiddenBeforeTheFirstPublication(): void
    {
        self::assertFalse($this->state(self::BEFORE_FIRST_PUBLICATION, hasUnseen: true)->isVisible());
    }

    public function testLinkIsShownToEveryoneFromTheFirstPublication(): void
    {
        self::assertTrue($this->state(self::AFTER_FIRST_PUBLICATION, hasUnseen: false)->isVisible());
    }

    public function testNoDotBeforeTheFirstPublication(): void
    {
        self::assertFalse($this->state(self::BEFORE_FIRST_PUBLICATION, hasUnseen: true)->hasUnseenReview(new User()));
    }

    public function testDotFollowsTheUnseenEligibleReviewOfTheLatestYear(): void
    {
        $repository = $this->createMock(YearInReviewRepository::class);
        $repository->expects(self::once())->method('existsUnseenEligibleForOwnerAndYear')->with(self::isInstanceOf(User::class), 2026)->willReturn(true);

        $state = new YearInReviewNavigationState($this->calendarAt(self::AFTER_FIRST_PUBLICATION), $repository);

        self::assertTrue($state->hasUnseenReview(new User()));
    }

    private function state(string $now, bool $hasUnseen): YearInReviewNavigationState
    {
        $repository = $this->createStub(YearInReviewRepository::class);
        $repository->method('existsUnseenEligibleForOwnerAndYear')->willReturn($hasUnseen);

        return new YearInReviewNavigationState($this->calendarAt($now), $repository);
    }

    private function calendarAt(string $now): YearInReviewCalendar
    {
        return new YearInReviewCalendar(new MockClock(new \DateTimeImmutable($now)));
    }
}
