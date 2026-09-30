<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Entity\User;
use App\Service\YearInReview\YearInReviewNavigationState;
use Twig\Attribute\AsTwigFunction;

/**
 * Lien « Mes résumés » de la sidebar : les règles vivent dans `YearInReviewNavigationState`,
 * partagé avec le panneau « Plus » mobile.
 */
final readonly class YearInReviewNavigationExtension
{
    public function __construct(
        private YearInReviewNavigationState $navigationState,
    ) {
    }

    #[AsTwigFunction('year_in_review_link_visible')]
    public function isLinkVisible(): bool
    {
        return $this->navigationState->isVisible();
    }

    #[AsTwigFunction('year_in_review_unseen')]
    public function hasUnseenReview(User $user): bool
    {
        return $this->navigationState->hasUnseenReview($user);
    }
}
