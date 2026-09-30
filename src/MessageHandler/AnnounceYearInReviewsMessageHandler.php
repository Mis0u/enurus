<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AnnounceYearInReviewMessage;
use App\Message\AnnounceYearInReviewsMessage;
use App\Repository\YearInReviewRepository;
use App\Service\YearInReview\YearInReviewCalendar;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class AnnounceYearInReviewsMessageHandler
{
    public function __construct(
        private YearInReviewCalendar $calendar,
        private YearInReviewRepository $yearInReviewRepository,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(AnnounceYearInReviewsMessage $message): void
    {
        $year = $this->calendar->latestPublishedYear();

        if (null === $year || ! $this->calendar->isAnnounced($year)) {
            return;
        }

        foreach ($this->yearInReviewRepository->findIdsAwaitingAnnouncement($year) as $yearInReviewId) {
            $this->bus->dispatch(new AnnounceYearInReviewMessage($yearInReviewId));
        }
    }
}
