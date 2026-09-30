<?php

declare(strict_types=1);

namespace App\Service\YearInReview;

use App\Entity\User;
use App\Repository\YearInReviewRepository;

/**
 * Bandeau « Ton année est prête » du dashboard de son propriétaire (jamais d'un dashboard partagé) :
 * de la publication au 31 janvier, tant que le résumé éligible n'a été ni ouvert ni masqué.
 */
final readonly class YearInReviewBannerState
{
    public function __construct(
        private YearInReviewCalendar $calendar,
        private YearInReviewRepository $yearInReviewRepository,
    ) {
    }

    public function yearToShow(User $user): ?int
    {
        $year = $this->calendar->latestPublishedYear();

        if (null === $year || ! $this->calendar->isBannerShown($year)) {
            return null;
        }

        $review = $this->yearInReviewRepository->findEligibleByOwnerAndYear($user, $year);

        return null !== $review && null === $review->seenAt && null === $review->bannerDismissedAt ? $year : null;
    }
}
