<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\GenerateUserYearInReviewMessage;
use App\Repository\UserRepository;
use App\Service\YearInReview\YearInReviewAnnouncer;
use App\Service\YearInReview\YearInReviewCalendar;
use App\Service\YearInReview\YearInReviewGenerator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Revérifie la publication : un message resté en file ne doit jamais figer une année trop tôt. Un
 * résumé figé après 7h (worker indisponible à minuit) est annoncé aussitôt : la tâche de 7h est
 * déjà passée sans lui.
 */
#[AsMessageHandler]
final readonly class GenerateUserYearInReviewMessageHandler
{
    public function __construct(
        private YearInReviewCalendar $calendar,
        private UserRepository $userRepository,
        private YearInReviewGenerator $generator,
        private YearInReviewAnnouncer $announcer,
    ) {
    }

    public function __invoke(GenerateUserYearInReviewMessage $message): void
    {
        if (! $this->calendar->isPublished($message->year)) {
            return;
        }

        $user = $this->userRepository->find(Uuid::fromString($message->userId));

        if (! $user instanceof User) {
            return;
        }

        $review = $this->generator->generate($user, $message->year);

        if (null !== $review && $this->calendar->isAnnounced($message->year)) {
            $this->announcer->announce($review);
        }
    }
}
