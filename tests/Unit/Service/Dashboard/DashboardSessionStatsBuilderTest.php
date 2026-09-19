<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\User;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardPeriod;
use App\Service\Dashboard\DashboardPeriods;
use App\Service\Dashboard\DashboardPrService;
use App\Service\Dashboard\DashboardSessionStatsBuilder;
use App\Service\Workout\WorkoutRecordDetectionService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DashboardSessionStatsBuilderTest extends TestCase
{
    private const int DAY_SESSIONS = 2;

    private const int ANNUAL_SESSIONS = 40;

    private const int WEEK_SESSIONS = 3;

    private const int MONTH_SESSIONS = 8;

    public function testBuildsTheStatsOfTheThreeFiltersAndTheAnnualTotal(): void
    {
        $stats = $this->createBuilder([])->build(new User(), $this->createPeriods(), self::DAY_SESSIONS);

        self::assertSame(2026, $stats['year']);
        self::assertSame(self::ANNUAL_SESSIONS, $stats['annualTotal']);
        self::assertSame(self::DAY_SESSIONS, $stats['last']['sessions']);
        self::assertSame(self::WEEK_SESSIONS, $stats['week']['sessions']);
        self::assertSame(self::MONTH_SESSIONS, $stats['month']['sessions']);
    }

    public function testEachFilterCarriesTheExerciseSetAndRepTotalsOfItsOwnPeriod(): void
    {
        $stats = $this->createBuilder([])->build(new User(), $this->createPeriods(), self::DAY_SESSIONS);

        self::assertSame(4, $stats['last']['exercises']);
        self::assertSame(12, $stats['last']['sets']);
        self::assertSame(100, $stats['last']['reps']);
        self::assertSame(9, $stats['week']['exercises']);
        self::assertSame(30, $stats['week']['sets']);
        self::assertSame(250, $stats['week']['reps']);
        self::assertSame(20, $stats['month']['exercises']);
        self::assertSame(90, $stats['month']['sets']);
        self::assertSame(800, $stats['month']['reps']);
    }

    public function testLabelsAreTranslatedInTheNavigationDomainWithTheirCount(): void
    {
        $stats = $this->createBuilder([])->build(new User(), $this->createPeriods(), self::DAY_SESSIONS);

        self::assertSame('dashboard.widget.session.sessions@navigation|{"count":2}', $stats['last']['sessionsLabel']);
        self::assertSame('dashboard.widget.session.exercises@navigation|{"count":4}', $stats['last']['exercisesLabel']);
        self::assertSame('workout.show.metrics.sets@navigation|{"count":12}', $stats['last']['setsLabel']);
        self::assertSame('dashboard.widget.session.sessions@navigation|{"count":3}', $stats['week']['sessionsLabel']);
        self::assertSame('dashboard.widget.session.sessions@navigation|{"count":8}', $stats['month']['sessionsLabel']);
    }

    public function testFiltersWithoutRecordUseTheDedicatedZeroLabelForPrs(): void
    {
        $stats = $this->createBuilder([])->build(new User(), $this->createPeriods(), self::DAY_SESSIONS);

        self::assertSame(0, $stats['last']['prCount']);
        self::assertSame('dashboard.widget.session.pr_count_zero@navigation|[]', $stats['last']['prLabel']);
        self::assertSame(0, $stats['last']['repsRecordCount']);
        self::assertSame('dashboard.widget.session.reps_record_count@navigation|{"count":0}', $stats['last']['repsRecordLabel']);
    }

    public function testRecordsAreCountedOnEveryFilterTheirDateFallsInto(): void
    {
        $event = [
            'workoutId' => 'workout-1',
            'performedAt' => new \DateTimeImmutable('2026-09-10 12:00:00'),
        ];

        $stats = $this->createBuilder([$event])->build(new User(), $this->createPeriods(), self::DAY_SESSIONS);

        foreach (['last', 'week', 'month'] as $filter) {
            self::assertSame(1, $stats[$filter]['prCount']);
            self::assertSame('dashboard.widget.session.pr_count@navigation|{"count":1}', $stats[$filter]['prLabel']);
            self::assertSame(1, $stats[$filter]['repsRecordCount']);
        }
    }

    private function createPeriods(): DashboardPeriods
    {
        return new DashboardPeriods(
            day: new DashboardPeriod(new \DateTimeImmutable('2026-09-10 00:00:00'), new \DateTimeImmutable('2026-09-10 23:59:59')),
            week: new DashboardPeriod(new \DateTimeImmutable('2026-09-07 00:00:00'), new \DateTimeImmutable('2026-09-13 23:59:59')),
            month: new DashboardPeriod(new \DateTimeImmutable('2026-09-01 00:00:00'), new \DateTimeImmutable('2026-09-18 23:59:59')),
            year: new DashboardPeriod(new \DateTimeImmutable('2026-01-01 00:00:00'), new \DateTimeImmutable('2026-09-18 23:59:59')),
        );
    }

    /**
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable}> $recordEvents
     */
    private function createBuilder(array $recordEvents): DashboardSessionStatsBuilder
    {
        $statsRepository = $this->createStub(WorkoutStatsRepository::class);
        $statsRepository->method('countByUserAndDate')->willReturnCallback(
            static fn (User $user, \DateTimeImmutable $start): int => match ($start->format('m-d')) {
                '01-01' => self::ANNUAL_SESSIONS,
                '09-07' => self::WEEK_SESSIONS,
                default => self::MONTH_SESSIONS,
            },
        );
        $statsRepository->method('findExerciseSetRepTotals')->willReturnCallback(
            static fn (User $user, ?\DateTimeImmutable $start): array => match ($start?->format('m-d')) {
                '09-10' => [
                    'exercises' => 4,
                    'sets' => 12,
                    'reps' => 100,
                ],
                '09-07' => [
                    'exercises' => 9,
                    'sets' => 30,
                    'reps' => 250,
                ],
                default => [
                    'exercises' => 20,
                    'sets' => 90,
                    'reps' => 800,
                ],
            },
        );

        $detectionService = $this->createStub(WorkoutRecordDetectionService::class);
        $detectionService->method('findPrEvents')->willReturn($recordEvents);
        $detectionService->method('findRepsRecordEvents')->willReturn($recordEvents);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null): string => \sprintf(
                '%s@%s|%s',
                $id,
                $domain,
                json_encode($parameters, JSON_THROW_ON_ERROR),
            ),
        );

        return new DashboardSessionStatsBuilder($statsRepository, new DashboardPrService($detectionService), $translator);
    }
}
