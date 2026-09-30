<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\GenerateUserYearInReviewMessage;
use App\Message\GenerateYearInReviewsMessage;
use App\MessageHandler\GenerateYearInReviewsMessageHandler;
use App\Repository\UserRepository;
use App\Service\YearInReview\YearInReviewCalendar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class GenerateYearInReviewsMessageHandlerTest extends TestCase
{
    public function testOneMessagePerUserStillWithoutReviewForTheLatestPublishedYear(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('findIdsWithoutYearInReview')->with(2026)->willReturn(['user-a', 'user-b']);
        $dispatched = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
            $dispatched[] = $message;

            return new Envelope($message);
        });

        $this->handler('2026-12-16 00:00:00 Europe/Paris', $userRepository, $bus)(new GenerateYearInReviewsMessage());

        self::assertEquals([
            new GenerateUserYearInReviewMessage('user-a', 2026),
            new GenerateUserYearInReviewMessage('user-b', 2026),
        ], $dispatched);
    }

    public function testNothingIsGeneratedBeforeTheFirstPublication(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::never())->method('findIdsWithoutYearInReview');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->handler('2026-12-15 23:59:59 Europe/Paris', $userRepository, $bus)(new GenerateYearInReviewsMessage());
    }

    private function handler(string $now, UserRepository $userRepository, MessageBusInterface $bus): GenerateYearInReviewsMessageHandler
    {
        return new GenerateYearInReviewsMessageHandler(
            new YearInReviewCalendar(new MockClock(new \DateTimeImmutable($now))),
            $userRepository,
            $bus,
        );
    }
}
