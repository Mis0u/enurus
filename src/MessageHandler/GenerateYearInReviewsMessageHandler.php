<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GenerateUserYearInReviewMessage;
use App\Message\GenerateYearInReviewsMessage;
use App\Repository\UserRepository;
use App\Service\YearInReview\YearInReviewCalendar;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class GenerateYearInReviewsMessageHandler
{
    public function __construct(
        private YearInReviewCalendar $calendar,
        private UserRepository $userRepository,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(GenerateYearInReviewsMessage $message): void
    {
        $year = $this->calendar->latestPublishedYear();

        if (null === $year) {
            return;
        }

        foreach ($this->userRepository->findIdsWithoutYearInReview($year) as $userId) {
            $this->bus->dispatch(new GenerateUserYearInReviewMessage($userId, $year));
        }
    }
}
