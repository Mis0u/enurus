<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\User;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Repository\WorkoutTonnageRepository;
use App\Service\Dashboard\DashboardPeriodCalculator;
use App\Service\Dashboard\DashboardTonnageChartBuilder;
use App\Service\Dashboard\DashboardTonnageService;
use App\Service\Utils\WeightConverterService;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Chartjs\Builder\ChartBuilder;

final class DashboardTonnageServiceTest extends TestCase
{
    public function testAnnualTotalSumsAllSeriesPointsConvertedToTheUsersUnit(): void
    {
        $user = $this->createUser(UnitOfMeasureEnum::KG);
        $now = new \DateTimeImmutable();
        $year = (new DashboardPeriodCalculator())->currentYearElapsed($now);

        $service = $this->service([
            [
                'performedAt' => $year->start,
                'tonnage' => 1000.0,
            ],
            [
                'performedAt' => $now,
                'tonnage' => 500.0,
            ],
        ]);

        $result = $service->getData($user, $user);

        self::assertSame(1500.0, $result['annualTotal']);
        self::assertSame('kg', $result['unit']);
    }

    public function testAnnualTotalIsConvertedToLbsForALbsUser(): void
    {
        $user = $this->createUser(UnitOfMeasureEnum::LBS);
        $now = new \DateTimeImmutable();

        $service = $this->service([
            [
                'performedAt' => $now,
                'tonnage' => 100.0,
            ],
        ]);

        $result = $service->getData($user, $user);

        // 100 kg -> round(100 * 2.20462, 1) = 220.5 lbs (WeightConverterService, cf. son propre test).
        self::assertSame(220.5, $result['annualTotal']);
        self::assertSame('lbs', $result['unit']);
    }

    public function testDailyChartHasOneBarPerDayOfTheElapsedYearEvenWithoutASession(): void
    {
        // Année tronquée à "aujourd'hui" (currentYearElapsed) : le nombre de barres attendu est
        // le nombre de jours écoulés depuis le 1er janvier inclus.
        $user = $this->createUser(UnitOfMeasureEnum::KG);
        $now = new \DateTimeImmutable();
        $year = (new DashboardPeriodCalculator())->currentYearElapsed($now);
        $expectedDays = (int) $year->start->diff($year->end)->days + 1;

        $service = $this->service([]);

        $result = $service->getData($user, $user);

        $sessionsChart = $result['charts']['sessions'];
        self::assertCount($expectedDays, $sessionsChart->getData()['labels']);
        self::assertCount($expectedDays, $sessionsChart->getData()['datasets'][0]['data']);
        self::assertSame(0.0, $sessionsChart->getData()['datasets'][0]['data'][0]);
        // array_column($points, 'label') doit extraire la seule chaîne de label, pas la structure
        // de point complète {label, value}.
        self::assertIsString($sessionsChart->getData()['labels'][0]);
    }

    public function testReturnedYearAndChartMinWidthsAreExactComputedIntegers(): void
    {
        $user = $this->createUser(UnitOfMeasureEnum::KG);
        $now = new \DateTimeImmutable();
        $expectedYear = (int) $now->format('Y');

        $service = $this->service([]);
        $result = $service->getData($user, $user);

        self::assertSame($expectedYear, $result['year']);

        $sessionsLabelCount = \count($result['charts']['sessions']->getData()['labels']);
        $weekLabelCount = \count($result['charts']['week']->getData()['labels']);

        self::assertSame($sessionsLabelCount * 28, $result['sessionsChartMinWidth']);
        self::assertSame($weekLabelCount * 40, $result['weekChartMinWidth']);
    }

    public function testMonthlyChartIncludesTheCurrentInProgressMonth(): void
    {
        // La borne de fin (aujourd'hui) tombe exactement sur le premier jour normalisé du bucket
        // du mois courant — seule une comparaison <= (et non <) inclut ce dernier bucket.
        $user = $this->createUser(UnitOfMeasureEnum::KG);
        $now = new \DateTimeImmutable();
        $expectedMonths = (int) $now->format('n');

        $service = $this->service([]);
        $result = $service->getData($user, $user);

        self::assertCount($expectedMonths, $result['charts']['month']->getData()['labels']);
    }

    public function testMultipleSessionsOnTheSameDayAreAggregatedOnASingleBar(): void
    {
        $user = $this->createUser(UnitOfMeasureEnum::KG);
        // Heure fixée à midi : un `+2 hours` doit rester le même jour calendaire, sinon ce test
        // devient flaky selon l'heure d'exécution (`+2 hours` traverserait minuit).
        $now = (new \DateTimeImmutable())->setTime(12, 0);

        $service = $this->service([
            [
                'performedAt' => $now,
                'tonnage' => 100.0,
            ],
            [
                'performedAt' => $now->modify('+2 hours'),
                'tonnage' => 50.0,
            ],
        ]);

        $result = $service->getData($user, $user);

        $lastBarValue = array_key_last($result['charts']['sessions']->getData()['datasets'][0]['data']);
        self::assertSame(150.0, $result['charts']['sessions']->getData()['datasets'][0]['data'][$lastBarValue]);
    }

    public function testDisplayUnitAndConversionFollowTheViewerNotTheOwner(): void
    {
        $owner = $this->createUser(UnitOfMeasureEnum::KG);
        $viewer = $this->createUser(UnitOfMeasureEnum::LBS);

        $service = $this->service([
            [
                'performedAt' => new \DateTimeImmutable(),
                'tonnage' => 100.0,
            ],
        ]);

        $result = $service->getData($owner, $viewer);

        self::assertSame(220.5, $result['annualTotal']);
        self::assertSame('lbs', $result['unit']);
    }

    public function testTonnageSeriesAreReadFromTheOwnerNotTheViewer(): void
    {
        $owner = $this->createUser(UnitOfMeasureEnum::KG);
        $viewer = $this->createUser(UnitOfMeasureEnum::KG);

        $workoutTonnageRepository = $this->createMock(WorkoutTonnageRepository::class);
        $workoutTonnageRepository->expects(self::once())
            ->method('findTonnageSeriesByUser')
            ->with($owner, self::anything(), self::anything())
            ->willReturn([]);

        $service = new DashboardTonnageService(
            $workoutTonnageRepository,
            new WeightConverterService(),
            new DashboardTonnageChartBuilder(new ChartBuilder()),
            new DashboardPeriodCalculator(),
        );

        $service->getData($owner, $viewer);
    }

    public function testChartLabelsAreFormattedInTheViewersLocaleNotTheOwners(): void
    {
        $owner = $this->createUser(UnitOfMeasureEnum::KG);
        $owner->locale = 'fr';
        $viewer = $this->createUser(UnitOfMeasureEnum::KG);
        $viewer->locale = 'en';

        $result = $this->service([])->getData($owner, $viewer);

        // Le graphique mensuel commence toujours en janvier : "Jan" en anglais, "janv." en français.
        self::assertSame('Jan', $result['charts']['month']->getData()['labels'][0]);
    }

    /**
     * @param array<int, array{performedAt: \DateTimeImmutable, tonnage: float}> $series
     */
    private function service(array $series): DashboardTonnageService
    {
        $workoutTonnageRepository = $this->createStub(WorkoutTonnageRepository::class);
        $workoutTonnageRepository->method('findTonnageSeriesByUser')->willReturn($series);

        return new DashboardTonnageService(
            $workoutTonnageRepository,
            new WeightConverterService(),
            new DashboardTonnageChartBuilder(new ChartBuilder()),
            new DashboardPeriodCalculator(),
        );
    }

    private function createUser(UnitOfMeasureEnum $unit): User
    {
        $user = new User();
        $user->unitOfMeasure = $unit;

        return $user;
    }
}
