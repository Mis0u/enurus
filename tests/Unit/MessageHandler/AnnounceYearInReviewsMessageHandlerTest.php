<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\AnnounceYearInReviewMessage;
use App\Message\AnnounceYearInReviewsMessage;
use App\MessageHandler\AnnounceYearInReviewsMessageHandler;
use App\Repository\YearInReviewRepository;
use App\Service\YearInReview\YearInReviewCalendar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class AnnounceYearInReviewsMessageHandlerTest extends TestCase
{
    public function testOneMessagePerReviewAwaitingItsEmailFromSevenInTheMorning(): void
    {
        $repository = $this->createMock(YearInReviewRepository::class);
        $repository->expects(self::once())->method('findIdsAwaitingAnnouncement')->with(2026)->willReturn(['review-a', 'review-b']);
        $dispatched = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            return new Envelope($message);
        });

        $this->handler('2026-12-16 07:00:00 Europe/Paris', $repository, $bus)(new AnnounceYearInReviewsMessage());

        self::assertEquals([new AnnounceYearInReviewMessage('review-a'), new AnnounceYearInReviewMessage('review-b')], $dispatched);
    }

    public function testNothingLeavesBeforeSevenInTheMorning(): void
    {
        $repository = $this->createMock(YearInReviewRepository::class);
        $repository->expects(self::never())->method('findIdsAwaitingAnnouncement');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->handler('2026-12-16 06:59:59 Europe/Paris', $repository, $bus)(new AnnounceYearInReviewsMessage());
    }

    private function handler(string $now, YearInReviewRepository $repository, MessageBusInterface $bus): AnnounceYearInReviewsMessageHandler
    {
        return new AnnounceYearInReviewsMessageHandler(new YearInReviewCalendar(new MockClock(new \DateTimeImmutable($now))), $repository, $bus);
    }
}
