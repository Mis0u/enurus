<?php

declare(strict_types=1);

namespace App\Service\YearInReview;

use App\Entity\User;
use App\Repository\YearInReviewRepository;

/**
 * Lien « Mes résumés » et son point bleu, partagés par la sidebar et le panneau « Plus » mobile.
 * Le lien existe pour tout le monde dès la première publication, jamais avant ; le point signale
 * seulement un résumé éligible pas encore ouvert (jamais le message d'encouragement).
 */
final readonly class YearInReviewNavigationState
{
    public function __construct(
        private YearInReviewCalendar $calendar,
        private YearInReviewRepository $yearInReviewRepository,
    ) {
    }

    public function isVisible(): bool
    {
        return null !== $this->calendar->latestPublishedYear();
    }

    public function hasUnseenReview(User $user): bool
    {
        $year = $this->calendar->latestPublishedYear();

        return null !== $year && $this->yearInReviewRepository->existsUnseenEligibleForOwnerAndYear($user, $year);
    }
}
