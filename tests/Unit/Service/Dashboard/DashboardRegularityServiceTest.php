<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\User;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardPeriodCalculator;
use App\Service\Dashboard\DashboardRegularityService;
use PHPUnit\Framework\TestCase;

/**
 * `DashboardRegularityService::getData()` instancie `new \DateTimeImmutable()` en interne (pas
 * injectable) : impossible de figer "maintenant" en test. Toutes les dates sont donc calculées
 * relativement au "maintenant" réel via `DashboardPeriodCalculator::weekStartOf()`, la même
 * fonction que le service utilise en interne, pour rester déterministe quel que soit le jour
 * d'exécution des tests.
 */
final class DashboardRegularityServiceTest extends TestCase
{
    public function testStreakCountsConsecutiveWeeksIncludingTheCurrentOne(): void
    {
        $weekStart = $this->currentWeekStart();
        $allDates = [
            $weekStart, // semaine en cours
            $weekStart->modify('-7 days'), // semaine -1
            $weekStart->modify('-14 days'), // semaine -2
            // trou volontaire avant : ne doit pas prolonger le streak.
            $weekStart->modify('-90 days'),
        ];

        $result = $this->getData($allDates);

        self::assertSame(3, $result['streak']);
    }

    public function testStreakFallsBackToPreviousWeekWhenCurrentWeekHasNoWorkoutYet(): void
    {
        $weekStart = $this->currentWeekStart();
        $allDates = [
            $weekStart->modify('-7 days'), // semaine précédente seulement
        ];

        $result = $this->getData($allDates);

        self::assertSame(1, $result['streak']);
    }

    public function testStreakIsZeroWithNoWorkoutAtAll(): void
    {
        $result = $this->getData([]);

        self::assertSame(0, $result['streak']);
        self::assertSame(0, $result['bestStreak']);
    }

    public function testBestStreakCanExceedTheCurrentOngoingStreak(): void
    {
        $weekStart = $this->currentWeekStart();
        $allDates = [
            // Streak record de 4 semaines consécutives, largement avant le streak en cours.
            $weekStart->modify('-70 days'),
            $weekStart->modify('-63 days'),
            $weekStart->modify('-56 days'),
            $weekStart->modify('-49 days'),
            // Streak en cours : une seule semaine.
            $weekStart,
        ];

        $result = $this->getData($allDates);

        self::assertSame(1, $result['streak']);
        self::assertSame(4, $result['bestStreak']);
    }

    public function testWeekCountOnlyCountsSessionsWithinTheCurrentWeek(): void
    {
        $weekStart = $this->currentWeekStart();
        $allDates = [
            $weekStart,
            $weekStart->modify('+1 day'),
            $weekStart->modify('-7 days'), // semaine précédente, ne doit pas compter
        ];

        $result = $this->getData($allDates);

        self::assertSame(2, $result['weekCount']);
    }

    public function testYearDeltaIsNullWithoutAnyPreviousYearActivity(): void
    {
        $result = $this->getData([new \DateTimeImmutable()], previousYearCount: 0);

        self::assertNull($result['yearDelta']);
    }

    public function testYearDeltaIsComputedWhenPreviousYearHadActivity(): void
    {
        $result = $this->getData([new \DateTimeImmutable()], previousYearCount: 5);

        self::assertSame(1 - 5, $result['yearDelta']);
    }

    private function currentWeekStart(): \DateTimeImmutable
    {
        return (new DashboardPeriodCalculator())->weekStartOf(new \DateTimeImmutable());
    }

    /**
     * @param \DateTimeImmutable[] $allDates
     * @return array{
     *     streak: int,
     *     bestStreak: int,
     *     weekCount: int,
     *     monthCount: int,
     *     yearCount: int,
     *     weekDelta: int,
     *     monthDelta: int,
     *     yearDelta: int|null,
     *     weekDays: array<int, array{date: \DateTimeImmutable, hasWorkout: bool, isToday: bool, isFuture: bool}>,
     *     previousWeekDays: array<int, array{date: \DateTimeImmutable, hasWorkout: bool, isToday: bool, isFuture: bool}>
     * }
     */
    private function getData(array $allDates, int $previousYearCount = 0): array
    {
        $workoutStatsRepository = $this->createStub(WorkoutStatsRepository::class);
        $workoutStatsRepository->method('findAllPerformedDatesByUser')->willReturn($allDates);
        // `countByUserAndDate` sert aux 3 deltas (semaine/mois/année) : seul yearDelta est
        // vérifié dans les tests qui utilisent un `$previousYearCount` non nul.
        $workoutStatsRepository->method('countByUserAndDate')->willReturn($previousYearCount);

        $service = new DashboardRegularityService($workoutStatsRepository, new DashboardPeriodCalculator());

        return $service->getData($this->createStub(User::class));
    }
}
