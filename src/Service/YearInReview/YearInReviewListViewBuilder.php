<?php

declare(strict_types=1);

namespace App\Service\YearInReview;

use App\Entity\User;
use App\Entity\YearInReview;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\YearInReview\YearInReviewCardStateEnum;
use App\Repository\YearInReviewRepository;
use App\Service\YearInReview\View\YearInReviewCard;

/**
 * Cartes de la page « Mes résumés », de l'année publiée la plus récente à la plus ancienne. Une
 * année publiée avant l'inscription n'a pas de carte ; une année sans résumé pour un compte qui
 * existait déjà est en cours de génération.
 */
final readonly class YearInReviewListViewBuilder
{
    private const int KG_PER_TONNE = 1000;

    public function __construct(
        private YearInReviewCalendar $calendar,
        private YearInReviewRepository $yearInReviewRepository,
    ) {
    }

    /**
     * @return list<YearInReviewCard>
     */
    public function cards(User $user): array
    {
        $latestYear = $this->calendar->latestPublishedYear();

        if (null === $latestYear) {
            return [];
        }

        $reviewsByYear = $this->reviewsByYear($user, $latestYear);
        $cards = [];

        foreach (range($latestYear, YearInReviewCalendar::FIRST_YEAR) as $year) {
            $card = $this->cardFor($user, $year, $reviewsByYear[$year] ?? null);

            if (null !== $card) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /**
     * @return array<int, YearInReview>
     */
    private function reviewsByYear(User $user, int $latestYear): array
    {
        $reviewsByYear = [];

        foreach ($this->yearInReviewRepository->findByOwnerUpToYear($user, $latestYear) as $review) {
            $reviewsByYear[$review->year] = $review;
        }

        return $reviewsByYear;
    }

    private function cardFor(User $user, int $year, ?YearInReview $review): ?YearInReviewCard
    {
        if (null !== $review) {
            return $this->storedCard($user, $review);
        }

        if ($user->createdAt < $this->calendar->publicationOf($year)) {
            return new YearInReviewCard($year, YearInReviewCardStateEnum::PENDING, 0, 0, 0, $user->unitOfMeasure);
        }

        return null;
    }

    private function storedCard(User $user, YearInReview $review): YearInReviewCard
    {
        $tonnageKg = $review->snapshot()->totals->tonnageKg ?? 0.0;

        return new YearInReviewCard(
            $review->year,
            $review->isEligible() ? YearInReviewCardStateEnum::READY : YearInReviewCardStateEnum::NOT_ENOUGH,
            $review->workoutCount,
            $review->missingWorkoutCount(),
            $this->tonnageInReaderUnit($tonnageKg, $user->unitOfMeasure),
            $user->unitOfMeasure,
            $review->isEligible() && null === $review->seenAt,
        );
    }

    private function tonnageInReaderUnit(float $tonnageKg, UnitOfMeasureEnum $unit): int
    {
        return (int) round(match ($unit) {
            UnitOfMeasureEnum::KG => $tonnageKg / self::KG_PER_TONNE,
            UnitOfMeasureEnum::LBS => $tonnageKg * UnitOfMeasureEnum::WEIGHT_IN_LBS,
        });
    }
}
