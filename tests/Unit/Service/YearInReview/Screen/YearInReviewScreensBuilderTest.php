<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview\Screen;

use App\Entity\MuscleGroup;
use App\Entity\User;
use App\Entity\YearInReview;
use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\Entity\Workout\WorkoutMoodEnum;
use App\Enum\YearInReview\TonnageEquivalenceEnum;
use App\Enum\YearInReview\YearInReviewScreenEnum;
use App\Enum\YearInReview\YearInReviewWeightUnitEnum;
use App\Repository\MuscleGroupRepository;
use App\Service\Badge\BadgeLabelFormatter;
use App\Service\Workout\HeatmapLevelCalculator;
use App\Service\YearInReview\Screen\RecapScreen;
use App\Service\YearInReview\Screen\SessionsScreen;
use App\Service\YearInReview\Screen\TonnageEquivalence;
use App\Service\YearInReview\Screen\TonnageScreen;
use App\Service\YearInReview\Screen\TopMuscleScreen;
use App\Service\YearInReview\Screen\YearInReviewHeatmapGridBuilder;
use App\Service\YearInReview\Screen\YearInReviewScreen;
use App\Service\YearInReview\Screen\YearInReviewScreensBuilder;
use App\Service\YearInReview\Screen\YearInReviewWeight;
use App\Service\YearInReview\Snapshot\YearInReviewBadge;
use App\Service\YearInReview\Snapshot\YearInReviewExercise;
use App\Service\YearInReview\Snapshot\YearInReviewHeaviestSet;
use App\Service\YearInReview\Snapshot\YearInReviewMood;
use App\Service\YearInReview\Snapshot\YearInReviewMuscle;
use App\Service\YearInReview\Snapshot\YearInReviewRecords;
use App\Service\YearInReview\Snapshot\YearInReviewRegularity;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;
use App\Service\YearInReview\YearInReviewCalendar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class YearInReviewScreensBuilderTest extends TestCase
{
    private const string MUSCLE_ID = '0199aaaa-0000-7000-8000-000000000001';

    public function testFullYearShowsEveryScreenInOrder(): void
    {
        $screens = $this->builder()->build($this->review($this->fullSnapshot()), $this->viewer());

        self::assertSame(YearInReviewScreenEnum::cases(), array_map(static fn (YearInReviewScreen $screen): YearInReviewScreenEnum => $screen->type(), $screens));
    }

    public function testScreensWithoutDataAreLeftOut(): void
    {
        $screens = $this->builder()->build($this->review($this->minimalSnapshot()), $this->viewer());

        self::assertSame(
            [YearInReviewScreenEnum::INTRO, YearInReviewScreenEnum::SESSIONS, YearInReviewScreenEnum::REPS, YearInReviewScreenEnum::REGULARITY, YearInReviewScreenEnum::HEATMAP, YearInReviewScreenEnum::RECAP, YearInReviewScreenEnum::THANKS],
            array_map(static fn (YearInReviewScreen $screen): YearInReviewScreenEnum => $screen->type(), $screens),
        );
        self::assertEquals(new RecapScreen(5, null, 150, null, 1, null, null, null, null), $this->screenOf($screens, RecapScreen::class));
    }

    public function testAverageWorkoutsPerWeekOverThePeriodWithOneDecimal(): void
    {
        $screens = $this->builder()->build($this->review($this->fullSnapshot()), $this->viewer());

        self::assertSame(2.8, $this->screenOf($screens, SessionsScreen::class)->averagePerWeek);
    }

    public function testTonnageIsComparedWithTheLargestObjectLiftedAtLeastOnce(): void
    {
        $screens = $this->builder()->build($this->review($this->fullSnapshot()), $this->viewer());

        self::assertEquals(
            new TonnageScreen(
                new YearInReviewWeight(312.0, YearInReviewWeightUnitEnum::TONNE),
                new TonnageEquivalence(TonnageEquivalenceEnum::AIRBUS_A380, 1, new YearInReviewWeight(277.0, YearInReviewWeightUnitEnum::TONNE)),
            ),
            $this->screenOf($screens, TonnageScreen::class),
        );
    }

    public function testPoundReaderSeesEveryWeightInPounds(): void
    {
        $viewer = $this->viewer();
        $viewer->unitOfMeasure = UnitOfMeasureEnum::LBS;

        $tonnage = $this->screenOf($this->builder()->build($this->review($this->fullSnapshot()), $viewer), TonnageScreen::class);

        self::assertSame(YearInReviewWeightUnitEnum::POUND, $tonnage->tonnage->unit);
        self::assertSame(YearInReviewWeightUnitEnum::POUND, $tonnage->equivalence?->referenceWeight->unit);
    }

    public function testMuscleRemovedFromTheReferenceIsLeftOut(): void
    {
        $screens = $this->builder(muscleExists: false)->build($this->review($this->fullSnapshot()), $this->viewer());

        self::assertNotContains(YearInReviewScreenEnum::TOP_MUSCLE, array_map(static fn (YearInReviewScreen $screen): YearInReviewScreenEnum => $screen->type(), $screens));
    }

    public function testTopMuscleIsPaintedOnTheReaderSilhouette(): void
    {
        $screens = $this->builder()->build($this->review($this->fullSnapshot()), $this->viewer());

        $muscle = $this->screenOf($screens, TopMuscleScreen::class);
        self::assertSame('pectoraux', $muscle->muscleName);
        self::assertSame(['chest-left', 'chest-right'], $muscle->svgIds);
    }

    public function testReviewBelowTheThresholdHasNoScreens(): void
    {
        $this->expectException(\LogicException::class);

        $this->builder()->build(YearInReview::notEligible($this->viewer(), 2026, 3), $this->viewer());
    }

    /**
     * @template T of YearInReviewScreen
     *
     * @param list<YearInReviewScreen> $screens
     * @param class-string<T>          $class
     *
     * @return T
     */
    private function screenOf(array $screens, string $class): YearInReviewScreen
    {
        foreach ($screens as $screen) {
            if ($screen instanceof $class) {
                return $screen;
            }
        }

        throw new \LogicException(\sprintf('No %s screen.', $class));
    }

    private function builder(bool $muscleExists = true): YearInReviewScreensBuilder
    {
        $muscleGroup = new MuscleGroup();
        $muscleGroup->name = 'pectoraux';
        $muscleGroup->svgIds = ['chest-left', 'chest-right'];
        $muscleGroupRepository = $this->createStub(MuscleGroupRepository::class);
        $muscleGroupRepository->method('find')->willReturn($muscleExists ? $muscleGroup : null);
        $badgeLabelFormatter = $this->createStub(BadgeLabelFormatter::class);
        $badgeLabelFormatter->method('name')->willReturn('100 séances');
        $badgeLabelFormatter->method('ribbon')->willReturn('100');

        return new YearInReviewScreensBuilder(
            new YearInReviewCalendar(new MockClock('2026-12-20 10:00:00')),
            $muscleGroupRepository,
            new YearInReviewHeatmapGridBuilder(new HeatmapLevelCalculator()),
            $badgeLabelFormatter,
        );
    }

    private function viewer(): User
    {
        return new User();
    }

    private function review(YearInReviewSnapshot $snapshot): YearInReview
    {
        return YearInReview::eligible($this->viewer(), 2026, $snapshot);
    }

    private function fullSnapshot(): YearInReviewSnapshot
    {
        return new YearInReviewSnapshot(
            new YearInReviewTotals(139, 2028, 16224, 312000.0),
            [
                '2026-01-05' => 4200.0,
            ],
            [new YearInReviewExercise('bench_press_bar.name', true, 94)],
            new YearInReviewMuscle(self::MUSCLE_ID, 572),
            new YearInReviewRecords(75, new YearInReviewHeaviestSet('deadlift.name', true, 172.5, new \DateTimeImmutable('2026-11-25'))),
            new YearInReviewRegularity(15, 3, 17),
            [new YearInReviewBadge(BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::GOLD)],
            new YearInReviewMood(WorkoutMoodEnum::EN_FORME, 50),
        );
    }

    private function minimalSnapshot(): YearInReviewSnapshot
    {
        return new YearInReviewSnapshot(
            new YearInReviewTotals(5, 15, 150, 0.0),
            [
                '2026-03-02' => 0.0,
            ],
            [],
            null,
            new YearInReviewRecords(0, null),
            new YearInReviewRegularity(1, 3, 5),
            [],
            null,
        );
    }
}
