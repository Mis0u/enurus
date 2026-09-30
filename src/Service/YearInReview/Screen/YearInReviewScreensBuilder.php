<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Screen;

use App\Entity\MuscleGroup;
use App\Entity\User;
use App\Entity\YearInReview;
use App\Enum\YearInReview\TonnageEquivalenceEnum;
use App\Repository\MuscleGroupRepository;
use App\Service\Badge\BadgeKey;
use App\Service\Badge\BadgeLabelFormatter;
use App\Service\Badge\View\BadgeTileView;
use App\Service\YearInReview\Snapshot\YearInReviewBadge;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use App\Service\YearInReview\YearInReviewCalendar;

/**
 * Écrans du parcours d'un résumé éligible, dans l'ordre de `YearInReviewScreenEnum`, depuis son
 * snapshot figé et dans la langue et l'unité de celui qui le regarde. Un écran sans donnée est
 * laissé de côté (et sa tuile de « En bref » avec lui).
 */
final readonly class YearInReviewScreensBuilder
{
    private const int DAYS_PER_WEEK = 7;

    public function __construct(
        private YearInReviewCalendar $calendar,
        private MuscleGroupRepository $muscleGroupRepository,
        private YearInReviewHeatmapGridBuilder $heatmapGridBuilder,
        private BadgeLabelFormatter $badgeLabelFormatter,
    ) {
    }

    /**
     * @return list<YearInReviewScreen>
     */
    public function build(YearInReview $review, User $viewer): array
    {
        $snapshot = $review->snapshot() ?? throw new \LogicException('Only an eligible year in review has screens.');

        $screens = array_values(array_filter([
            new IntroScreen($review->year),
            $this->sessionsScreen($review->year, $snapshot),
            $this->tonnageScreen($snapshot, $viewer),
            new RepsScreen($snapshot->totals->repCount, $snapshot->totals->setCount),
            [] === $snapshot->topExercises ? null : new TopExercisesScreen($snapshot->topExercises),
            $this->topMuscleScreen($snapshot, $viewer),
            $this->recordsScreen($snapshot, $viewer),
            $this->regularityScreen($review->year, $snapshot),
            new HeatmapScreen(\count($snapshot->tonnageKgByDay), $this->heatmapGridBuilder->build($snapshot->tonnageKgByDay, $this->calendar->periodOf($review->year))),
            $this->badgesScreen($review->year, $snapshot, $viewer),
            null === $snapshot->dominantMood ? null : new MoodScreen($snapshot->dominantMood->mood, $snapshot->dominantMood->percent),
        ]));

        return [...$screens, $this->recapScreen($screens, $snapshot), new ThanksScreen($review->year)];
    }

    private function sessionsScreen(int $year, YearInReviewSnapshot $snapshot): SessionsScreen
    {
        $period = $this->calendar->periodOf($year);
        $weekCount = ((int) $period->start->diff($period->end)->days + 1) / self::DAYS_PER_WEEK;

        return new SessionsScreen($snapshot->totals->workoutCount, round($snapshot->totals->workoutCount / $weekCount, 1));
    }

    private function tonnageScreen(YearInReviewSnapshot $snapshot, User $viewer): ?TonnageScreen
    {
        $tonnageKg = $snapshot->totals->tonnageKg;

        if (0.0 >= $tonnageKg) {
            return null;
        }

        $object = TonnageEquivalenceEnum::largestLiftedBy($tonnageKg);
        $equivalence = null === $object ? null : new TonnageEquivalence(
            $object,
            $object->timesIn($tonnageKg),
            YearInReviewWeight::reference($object->weightKg(), $viewer->unitOfMeasure),
        );

        return new TonnageScreen(YearInReviewWeight::tonnage($tonnageKg, $viewer->unitOfMeasure), $equivalence);
    }

    private function topMuscleScreen(YearInReviewSnapshot $snapshot, User $viewer): ?TopMuscleScreen
    {
        $muscleGroup = null === $snapshot->topMuscle ? null : $this->muscleGroupRepository->find($snapshot->topMuscle->muscleGroupId);

        if (! $muscleGroup instanceof MuscleGroup || null === $snapshot->topMuscle) {
            return null;
        }

        return new TopMuscleScreen($muscleGroup->name, $snapshot->topMuscle->setCount, array_values($muscleGroup->svgIds), $viewer->gender);
    }

    private function recordsScreen(YearInReviewSnapshot $snapshot, User $viewer): ?RecordsScreen
    {
        $records = $snapshot->records;

        if (0 === $records->count) {
            return null;
        }

        $heaviestSet = null === $records->heaviestSet ? null : new HeaviestSetView(
            $records->heaviestSet->exerciseName,
            $records->heaviestSet->isPublicExercise,
            YearInReviewWeight::lifted($records->heaviestSet->weightKg, $viewer->unitOfMeasure),
            $records->heaviestSet->performedOn,
        );

        return new RecordsScreen($records->count, $heaviestSet);
    }

    private function regularityScreen(int $year, YearInReviewSnapshot $snapshot): RegularityScreen
    {
        $regularity = $snapshot->regularity;

        return new RegularityScreen(
            $regularity->longestStreakWeeks,
            new \DateTimeImmutable(\sprintf('%d-%02d-01', $year, $regularity->busiestMonth)),
            $regularity->busiestMonthWorkoutCount,
        );
    }

    private function badgesScreen(int $year, YearInReviewSnapshot $snapshot, User $viewer): ?BadgesScreen
    {
        if ([] === $snapshot->badges) {
            return null;
        }

        // Date réelle non figée : seule compte l'obtention dans l'année (badge affiché « obtenu »).
        $earnedDuringTheYear = $this->calendar->periodOf($year)->end;

        return new BadgesScreen(array_map(
            fn (YearInReviewBadge $badge): BadgeTileView => $this->badgeTile($badge, $viewer, $earnedDuringTheYear),
            $snapshot->badges,
        ));
    }

    private function badgeTile(YearInReviewBadge $badge, User $viewer, \DateTimeImmutable $earnedAt): BadgeTileView
    {
        $key = new BadgeKey($badge->family, $badge->tier);

        return new BadgeTileView($badge->family, $badge->tier, $this->badgeLabelFormatter->name($key, $viewer), $this->badgeLabelFormatter->ribbon($key, $viewer), $earnedAt);
    }

    /**
     * @param list<YearInReviewScreen> $screens
     */
    private function recapScreen(array $screens, YearInReviewSnapshot $snapshot): RecapScreen
    {
        return new RecapScreen(
            $snapshot->totals->workoutCount,
            $this->shown($screens, TonnageScreen::class)?->tonnage,
            $snapshot->totals->repCount,
            $this->shown($screens, RecordsScreen::class)?->count,
            $snapshot->regularity->longestStreakWeeks,
            null === $this->shown($screens, BadgesScreen::class) ? null : \count($snapshot->badges),
            $snapshot->topExercises[0] ?? null,
            $this->shown($screens, TopMuscleScreen::class)?->muscleName,
            $snapshot->dominantMood?->mood,
        );
    }

    /**
     * @template T of YearInReviewScreen
     *
     * @param list<YearInReviewScreen> $screens
     * @param class-string<T>          $class
     *
     * @return T|null
     */
    private function shown(array $screens, string $class): ?YearInReviewScreen
    {
        foreach ($screens as $screen) {
            if ($screen instanceof $class) {
                return $screen;
            }
        }

        return null;
    }
}
