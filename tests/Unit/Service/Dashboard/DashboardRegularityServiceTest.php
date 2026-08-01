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

    public function testWeekBoundariesAreInclusive(): void
    {
        $week = (new DashboardPeriodCalculator())->currentWeek(new \DateTimeImmutable());
        $allDates = [
            $week->start,
            $week->end,
            $week->start->modify('-1 second'),
            $week->end->modify('+1 second'),
        ];

        $result = $this->getData($allDates);

        self::assertSame(2, $result['weekCount']);
    }

    public function testPreviousWeekBoundariesAreInclusiveAndMarkWorkoutDaySet(): void
    {
        $previousWeek = (new DashboardPeriodCalculator())->previousWeek(new \DateTimeImmutable());
        $allDates = [
            $previousWeek->start,
            $previousWeek->end,
            $previousWeek->start->modify('-1 second'),
            $previousWeek->end->modify('+1 second'),
        ];

        $result = $this->getData($allDates);

        // previousWeek n'alimente aucun compteur retourné directement, seul workoutDaySet — visible
        // via previousWeekDays['hasWorkout'] pour les jours au sein de la période précédente.
        $hasWorkoutByDate = [];
        foreach ($result['previousWeekDays'] as $day) {
            $hasWorkoutByDate[$day['date']->format('Y-m-d')] = $day['hasWorkout'];
        }

        self::assertTrue($hasWorkoutByDate[$previousWeek->start->format('Y-m-d')]);
        self::assertTrue($hasWorkoutByDate[$previousWeek->end->format('Y-m-d')]);
    }

    public function testMonthBoundariesAreInclusive(): void
    {
        $month = (new DashboardPeriodCalculator())->currentMonthElapsed(new \DateTimeImmutable());
        $allDates = [
            $month->start,
            $month->end,
            $month->start->modify('-1 second'),
        ];

        $result = $this->getData($allDates);

        self::assertSame(2, $result['monthCount']);
    }

    public function testYearBoundariesAreInclusive(): void
    {
        $year = (new DashboardPeriodCalculator())->currentYearElapsed(new \DateTimeImmutable());
        $allDates = [
            $year->start,
            $year->end,
            $year->start->modify('-1 second'),
        ];

        $result = $this->getData($allDates);

        self::assertSame(2, $result['yearCount']);
    }

    public function testWeekAndMonthDeltasAreDifferencesNotSums(): void
    {
        // Même date en double (2 séances le même jour) plutôt que 2 jours distincts. Utiliser
        // "aujourd'hui" plutôt que le lundi de la semaine en cours : ce lundi peut appartenir au
        // mois précédent (ex. le 1er du mois tombant un samedi, comme le 2026-08-01), auquel cas
        // il tomberait hors de currentMonthElapsed() (borné à [1er du mois, aujourd'hui]) et
        // ferait échouer monthDelta pour une raison sans rapport avec ce que le test vérifie.
        // "today" est en revanche toujours dans la semaine en cours ET dans le mois élapsed.
        $today = new \DateTimeImmutable('today');
        $allDates = [$today, $today];

        $result = $this->getData($allDates, previousCount: 5);

        // weekCount=2, monthCount=2, previousCount=5 pour les deux : une confusion +/- serait
        // indétectable si delta était nul des deux côtés, d'où des valeurs délibérément non nulles
        // et non symétriques (2-5=-3, 2+5=7 : sans ambiguïté).
        self::assertSame(2 - 5, $result['weekDelta']);
        self::assertSame(2 - 5, $result['monthDelta']);
    }

    public function testWeekDaysStructureMatchesWorkoutDaysAndTodayAndFuture(): void
    {
        $weekStart = $this->currentWeekStart();
        $today = new \DateTimeImmutable('today');
        $todayIndex = ((int) $today->format('N')) - 1;

        // Un jour avec séance, choisi différent d'aujourd'hui pour ne pas se confondre avec le
        // marquage isToday (+3 mod 7 ne coïncide jamais avec l'index de départ sur 7 valeurs).
        $workoutIndex = ($todayIndex + 3) % 7;
        $allDates = [$weekStart->modify(sprintf('+%d days', $workoutIndex))];

        $result = $this->getData($allDates);

        self::assertCount(7, $result['weekDays']);

        foreach ($result['weekDays'] as $index => $day) {
            $expectedDate = $weekStart->modify(sprintf('+%d days', $index));
            self::assertSame($expectedDate->format('Y-m-d'), $day['date']->format('Y-m-d'));
            self::assertSame($workoutIndex === $index, $day['hasWorkout']);
            self::assertSame($todayIndex === $index, $day['isToday']);
            self::assertSame($index > $todayIndex, $day['isFuture']);
        }
    }

    public function testBestStreakDeduplicatesSameCalendarWeekRegardlessOfWeekdayAndInputOrder(): void
    {
        // 3 semaines consécutives (lundis 2026-01-05, 2026-01-12, 2026-01-19), fournies sur des
        // jours de semaine différents (mardi/jeudi/mercredi/lundi) et dans un ordre chronologique
        // volontairement mélangé — la déduplication par semaine ISO et le tri interne doivent
        // reconstituer les 3 semaines correctement malgré ça.
        $allDates = [
            new \DateTimeImmutable('2026-01-19'), // lundi, semaine 3
            new \DateTimeImmutable('2026-01-06'), // mardi, semaine 1
            new \DateTimeImmutable('2026-01-14'), // mercredi, semaine 2
            new \DateTimeImmutable('2026-01-08'), // jeudi, même semaine 1 que le mardi ci-dessus
        ];

        $result = $this->getData($allDates);

        self::assertSame(3, $result['bestStreak']);
    }

    public function testBestStreakStaysAtOneWithNoConsecutiveWeeks(): void
    {
        $allDates = [
            new \DateTimeImmutable('2026-01-05'),
            new \DateTimeImmutable('2026-02-02'), // 28 jours plus tard : jamais 2 semaines consécutives
        ];

        $result = $this->getData($allDates);

        self::assertSame(1, $result['bestStreak']);
    }

    public function testBestStreakResetAfterAGapDoesNotInflateTheNextMatch(): void
    {
        $allDates = [
            new \DateTimeImmutable('2026-01-05'),
            new \DateTimeImmutable('2026-01-26'), // 21 jours plus tard : pas un match, reset
            new \DateTimeImmutable('2026-02-02'), // 7 jours après la précédente : 1 seul match
        ];

        $result = $this->getData($allDates);

        self::assertSame(2, $result['bestStreak']);
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
    private function getData(array $allDates, int $previousYearCount = 0, int $previousCount = 0): array
    {
        $workoutStatsRepository = $this->createStub(WorkoutStatsRepository::class);
        $workoutStatsRepository->method('findAllPerformedDatesByUser')->willReturn($allDates);
        // `countByUserAndDate` sert aux 3 deltas (semaine/mois/année) : seul yearDelta est
        // vérifié dans les tests qui utilisent un `$previousYearCount` non nul.
        $workoutStatsRepository->method('countByUserAndDate')->willReturn(0 !== $previousCount ? $previousCount : $previousYearCount);

        $service = new DashboardRegularityService($workoutStatsRepository, new DashboardPeriodCalculator());

        return $service->getData($this->createStub(User::class));
    }
}
