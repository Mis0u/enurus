<?php

declare(strict_types=1);

namespace App\Service\YearInReview;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

/**
 * Déclarée uniquement sous `when@dev` (`config/services.yaml`) : permet de prévisualiser le résumé
 * annuel avant le 16 décembre via `YEAR_IN_REVIEW_FAKE_NOW`, sans fausser l'horloge du reste de
 * l'appli (tokens, dates de connexion…). En prod et en test, la variable n'est jamais lue.
 */
final class YearInReviewClockFactory
{
    public static function create(ClockInterface $realClock, ?string $fakeNow): ClockInterface
    {
        if (null === $fakeNow || '' === $fakeNow) {
            return $realClock;
        }

        return new MockClock($fakeNow);
    }
}
