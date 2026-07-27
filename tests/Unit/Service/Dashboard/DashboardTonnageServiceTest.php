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

        $result = $service->getData($user);

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

        $result = $service->getData($user);

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

        $result = $service->getData($user);

        $sessionsChart = $result['charts']['sessions'];
        self::assertCount($expectedDays, $sessionsChart->getData()['labels']);
        self::assertCount($expectedDays, $sessionsChart->getData()['datasets'][0]['data']);
        self::assertSame(0.0, $sessionsChart->getData()['datasets'][0]['data'][0]);
    }

    public function testMultipleSessionsOnTheSameDayAreAggregatedOnASingleBar(): void
    {
        $user = $this->createUser(UnitOfMeasureEnum::KG);
        $now = new \DateTimeImmutable();

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

        $result = $service->getData($user);

        $lastBarValue = array_key_last($result['charts']['sessions']->getData()['datasets'][0]['data']);
        self::assertSame(150.0, $result['charts']['sessions']->getData()['datasets'][0]['data'][$lastBarValue]);
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
