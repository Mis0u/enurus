<?php

declare(strict_types=1);

namespace App\Service\YearInReview;

use App\Entity\YearInReview;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Première ouverture des écrans d'un résumé : éteint son point bleu et sa pastille « Nouveau ».
 * Vrai instant, donc l'horloge réelle, jamais la date simulée du résumé.
 */
final readonly class YearInReviewSeenMarker
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    public function markSeen(YearInReview $review): void
    {
        if (null !== $review->seenAt) {
            return;
        }

        $review->seenAt = $this->clock->now();
        $this->em->flush();
    }
}
