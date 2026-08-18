<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Exercise;

use App\Entity\Exercise;
use App\Entity\User;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Repository\ExerciseSetRepository;
use App\Service\Exercise\ExerciseHistoryChartBuilder;
use App\Service\Exercise\ExerciseHistoryDataService;
use App\Service\Utils\WeightConverterService;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Chartjs\Builder\ChartBuilder;

final class ExerciseHistoryDataServiceTest extends TestCase
{
    public function testAnExerciseWithNoHistoryReturnsAZeroedOverviewAndOnlyTheAllPeriod(): void
    {
        $service = $this->service([]);

        $result = $service->getData($this->user(UnitOfMeasureEnum::KG), $this->weightExercise(), 'all', 1, 10);

        self::assertFalse($result['hasHistory']);
        self::assertSame(0, $result['overview']['totalSessions']);
        self::assertSame(['all'], $result['availablePeriods']);
    }

    public function testTheBestSetOfASessionKeepsItsOwnRepsRatherThanAnIndependentMaxAndSumAggregate(): void
    {
        // 80kg×10 + 90kg×8 + 100kg×5 sur la même séance : la "série max" doit être 100kg × 5,
        // jamais 100kg (MAX poids) associé à 23 (SUM reps) — cf. CLAUDE.md.
        $rawSets = [
            $this->rawSet('workout-1', weight: 80.0, reps: 10),
            $this->rawSet('workout-1', weight: 90.0, reps: 8),
            $this->rawSet('workout-1', weight: 100.0, reps: 5),
        ];

        $service = $this->service($rawSets);
        $result = $service->getData($this->user(UnitOfMeasureEnum::KG), $this->weightExercise(), 'all', 1, 10);

        self::assertSame(100.0, $result['overview']['recordValue']);
    }

    public function testSessionVolumeIsTheSumOfWeightTimesRepsAcrossAllItsSets(): void
    {
        $rawSets = [
            $this->rawSet('workout-1', weight: 80.0, reps: 10),
            $this->rawSet('workout-1', weight: 90.0, reps: 8),
        ];

        $service = $this->service($rawSets);
        $result = $service->getData($this->user(UnitOfMeasureEnum::KG), $this->weightExercise(), 'all', 1, 10);

        // 80*10 + 90*8 = 1520
        self::assertSame(1520.0, $result['overview']['totalVolume']);
    }

    public function testTheFirstSessionAlwaysCountsAsTheCurrentRecordWhenNoLaterSessionBeatsIt(): void
    {
        $firstSessionDate = new \DateTimeImmutable('-2 days');
        $rawSets = [
            $this->rawSet('workout-1', weight: 100.0, reps: 5, performedAt: $firstSessionDate),
            $this->rawSet('workout-2', weight: 100.0, reps: 5, performedAt: new \DateTimeImmutable('now')),
        ];

        $service = $this->service($rawSets);
        $result = $service->getData($this->user(UnitOfMeasureEnum::KG), $this->weightExercise(), 'all', 1, 10);

        // La 2e séance égale (jamais dépasse) le record : le record actuel reste celui du 1er jour.
        self::assertEquals($firstSessionDate, $result['overview']['recordDate']);
    }

    public function testASessionStrictlyBeatingThePreviousRecordBecomesTheNewCurrentRecord(): void
    {
        $rawSets = [
            $this->rawSet('workout-1', weight: 100.0, reps: 5, performedAt: new \DateTimeImmutable('-2 days')),
            $this->rawSet('workout-2', weight: 110.0, reps: 5, performedAt: new \DateTimeImmutable('now')),
        ];

        $service = $this->service($rawSets);
        $result = $service->getData($this->user(UnitOfMeasureEnum::KG), $this->weightExercise(), 'all', 1, 10);

        self::assertSame(110.0, $result['overview']['recordValue']);
    }

    public function testRecordAndVolumeAreConvertedToTheUsersUnit(): void
    {
        $rawSets = [$this->rawSet('workout-1', weight: 100.0, reps: 1)];

        $service = $this->service($rawSets);
        $result = $service->getData($this->user(UnitOfMeasureEnum::LBS), $this->weightExercise(), 'all', 1, 10);

        // 100 kg -> round(100 * 2.20462, 1) = 220.5 lbs (WeightConverterService).
        self::assertSame(220.5, $result['overview']['recordValue']);
        self::assertSame('lbs', $result['unitLabel']);
    }

    public function testAvailablePeriodsOnlyIncludeWindowsActuallyCoveredByTheHistory(): void
    {
        $rawSets = [$this->rawSet('workout-1', weight: 100.0, reps: 5, performedAt: new \DateTimeImmutable('-1 week'))];

        $service = $this->service($rawSets);
        $result = $service->getData($this->user(UnitOfMeasureEnum::KG), $this->weightExercise(), 'all', 1, 10);

        self::assertSame(['all'], $result['availablePeriods']);
    }

    public function testAvailablePeriodsIncludeThreeMonthsAndOneYearWhenTheHistoryIsOldEnough(): void
    {
        $rawSets = [$this->rawSet('workout-1', weight: 100.0, reps: 5, performedAt: new \DateTimeImmutable('-2 years'))];

        $service = $this->service($rawSets);
        $result = $service->getData($this->user(UnitOfMeasureEnum::KG), $this->weightExercise(), 'all', 1, 10);

        self::assertSame(['all', '3m', '1y'], $result['availablePeriods']);
    }

    public function testAnUnavailablePeriodFallsBackToAll(): void
    {
        $rawSets = [$this->rawSet('workout-1', weight: 100.0, reps: 5, performedAt: new \DateTimeImmutable('-1 week'))];

        $service = $this->service($rawSets);
        $result = $service->getData($this->user(UnitOfMeasureEnum::KG), $this->weightExercise(), '1y', 1, 10);

        self::assertSame('all', $result['period']);
    }

    public function testTheJournalIsPassedToThePaginatorMostRecentSessionFirst(): void
    {
        $rawSets = [
            $this->rawSet('workout-older', weight: 100.0, reps: 5, performedAt: new \DateTimeImmutable('-2 days')),
            $this->rawSet('workout-newer', weight: 110.0, reps: 5, performedAt: new \DateTimeImmutable('now')),
        ];

        $captured = null;
        $paginator = $this->createMock(PaginatorInterface::class);
        $paginator->expects(self::once())
            ->method('paginate')
            ->willReturnCallback(function (array $items) use (&$captured): PaginationInterface {
                $captured = $items;

                return $this->createStub(PaginationInterface::class);
            });

        $service = $this->service($rawSets, $paginator);
        $service->getData($this->user(UnitOfMeasureEnum::KG), $this->weightExercise(), 'all', 1, 10);

        self::assertNotNull($captured);
        self::assertSame('workout-newer', $captured[0]['workoutId']);
        self::assertSame('workout-older', $captured[1]['workoutId']);
    }

    public function testATimeBasedExerciseUsesRawSecondsForTheOverviewRecord(): void
    {
        // Minutes décimales arrondies (480s -> 8.0min) sont sans ambiguïté ici, mais le choix de
        // secondes brutes est vérifié par le test suivant sur un cas qui ne tombe pas rond.
        $rawSets = [$this->rawSet('workout-1', weight: 0.0, reps: 0, duration: 480)];

        $service = $this->service($rawSets);
        $result = $service->getData($this->user(UnitOfMeasureEnum::KG), $this->timeExercise(), 'all', 1, 10);

        self::assertSame(480, $result['overview']['recordValue']);
        self::assertSame('min', $result['unitLabel']);
    }

    public function testATimeBasedExerciseKeepsExactSecondsRatherThanARoundedMinutesValue(): void
    {
        // 135s = 2min15 — en minutes décimales arrondies ça donnerait "2,3 min", trompeur : la
        // tuile doit garder les secondes brutes pour un formatage mm:ss fidèle par le template.
        $rawSets = [$this->rawSet('workout-1', weight: 0.0, reps: 0, duration: 135)];

        $service = $this->service($rawSets);
        $result = $service->getData($this->user(UnitOfMeasureEnum::KG), $this->timeExercise(), 'all', 1, 10);

        self::assertSame(135, $result['overview']['recordValue']);
    }

    public function testADistanceBasedExerciseUsesRawMetersForTheOverviewRecord(): void
    {
        $rawSets = [$this->rawSet('workout-1', weight: 0.0, reps: 0, distance: 500)];

        $service = $this->service($rawSets);
        $result = $service->getData($this->user(UnitOfMeasureEnum::KG), $this->distanceExercise(), 'all', 1, 10);

        self::assertSame(500.0, $result['overview']['recordValue']);
        self::assertSame('m', $result['unitLabel']);
    }

    /**
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable, weight: float, reps: int, duration: ?int, distance: ?int}> $rawSets
     */
    private function service(array $rawSets, ?PaginatorInterface $paginator = null): ExerciseHistoryDataService
    {
        $repository = $this->createStub(ExerciseSetRepository::class);
        $repository->method('findSessionHistoryForExerciseAndUser')->willReturn($rawSets);

        return new ExerciseHistoryDataService(
            $repository,
            new WeightConverterService(),
            new ExerciseHistoryChartBuilder(new ChartBuilder()),
            $paginator ?? $this->stubPaginator(),
        );
    }

    private function stubPaginator(): PaginatorInterface
    {
        $paginator = $this->createStub(PaginatorInterface::class);
        $paginator->method('paginate')->willReturn($this->createStub(PaginationInterface::class));

        return $paginator;
    }

    /**
     * @return array{workoutId: string, performedAt: \DateTimeImmutable, weight: float, reps: int, duration: ?int, distance: ?int}
     */
    private function rawSet(
        string $workoutId,
        float $weight,
        int $reps,
        ?int $duration = null,
        ?int $distance = null,
        ?\DateTimeImmutable $performedAt = null,
    ): array {
        return [
            'workoutId' => $workoutId,
            'performedAt' => $performedAt ?? new \DateTimeImmutable('now'),
            'weight' => $weight,
            'reps' => $reps,
            'duration' => $duration,
            'distance' => $distance,
        ];
    }

    private function weightExercise(): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Squat';
        $exercise->measurementType = MeasurementType::WEIGHT_REPS;

        return $exercise;
    }

    private function timeExercise(): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Plank';
        $exercise->measurementType = MeasurementType::TIME;

        return $exercise;
    }

    private function distanceExercise(): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Farmer walk';
        $exercise->measurementType = MeasurementType::DISTANCE;

        return $exercise;
    }

    private function user(UnitOfMeasureEnum $unit): User
    {
        $user = new User();
        $user->unitOfMeasure = $unit;
        $user->locale = 'fr';

        return $user;
    }
}
