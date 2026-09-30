<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\YearInReview;
use App\Message\AnnounceYearInReviewMessage;
use App\Repository\YearInReviewRepository;
use App\Service\YearInReview\YearInReviewAnnouncer;
use App\Service\YearInReview\YearInReviewCalendar;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Revérifie l'heure d'envoi : un message resté en file ne doit jamais partir avant 7h.
 */
#[AsMessageHandler]
final readonly class AnnounceYearInReviewMessageHandler
{
    public function __construct(
        private YearInReviewCalendar $calendar,
        private YearInReviewRepository $yearInReviewRepository,
        private YearInReviewAnnouncer $announcer,
    ) {
    }

    public function __invoke(AnnounceYearInReviewMessage $message): void
    {
        $review = $this->yearInReviewRepository->find(Uuid::fromString($message->yearInReviewId));

        if ($review instanceof YearInReview && $this->calendar->isAnnounced($review->year)) {
            $this->announcer->announce($review);
        }
    }
}
