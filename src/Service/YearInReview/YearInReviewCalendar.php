<?php

declare(strict_types=1);

namespace App\Service\YearInReview;

use App\Service\Dashboard\DashboardPeriod;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Seule source des dates du résumé annuel : période comptée (1er janvier → 15 décembre) et moment
 * de publication (16 décembre à minuit, heure de Paris — site français, un seul envoi pour tous).
 * Avant publication, rien du résumé ne doit exister côté utilisateur (lien, page, route).
 *
 * L'horloge injectée est la vraie, sauf en dev où `YEAR_IN_REVIEW_FAKE_NOW` peut simuler une date
 * (`YearInReviewClockFactory`) pour prévisualiser le résumé avant décembre.
 */
final readonly class YearInReviewCalendar
{
    public const int FIRST_YEAR = 2026;

    private const string PUBLICATION_TIMEZONE = 'Europe/Paris';

    public function __construct(
        #[Autowire(service: 'app.year_in_review.clock')]
        private ClockInterface $clock,
    ) {
    }

    public function periodOf(int $year): DashboardPeriod
    {
        return new DashboardPeriod(
            new \DateTimeImmutable(\sprintf('%d-01-01 00:00:00', $year)),
            new \DateTimeImmutable(\sprintf('%d-12-15 23:59:59', $year)),
        );
    }

    public function isPublished(int $year): bool
    {
        return self::FIRST_YEAR <= $year && $this->publicationOf($year) <= $this->clock->now();
    }

    public function latestPublishedYear(): ?int
    {
        $currentYear = (int) $this->clock->now()->setTimezone(new \DateTimeZone(self::PUBLICATION_TIMEZONE))->format('Y');
        $latestYear = $this->isPublished($currentYear) ? $currentYear : $currentYear - 1;

        return self::FIRST_YEAR <= $latestYear ? $latestYear : null;
    }

    private function publicationOf(int $year): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\sprintf('%d-12-16 00:00:00', $year), new \DateTimeZone(self::PUBLICATION_TIMEZONE));
    }
}
