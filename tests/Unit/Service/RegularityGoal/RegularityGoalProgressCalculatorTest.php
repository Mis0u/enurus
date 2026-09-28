<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\RegularityGoal;

use App\Entity\RegularityGoal;
use App\Enum\Entity\RegularityGoal\RegularityGoalDurationEnum;
use App\Enum\Entity\RegularityGoal\RegularityGoalPeriodEnum;
use App\Service\RegularityGoal\RegularityGoalPeriodStatusEnum;
use App\Service\RegularityGoal\RegularityGoalProgress;
use App\Service\RegularityGoal\RegularityGoalProgressCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Dates fixes : les défis hebdomadaires démarrent par défaut le lundi 5 octobre 2026.
 */
final class RegularityGoalProgressCalculatorTest extends TestCase
{
    public function testAWeeklyGoalIsSplitIntoPeriodsOfSevenDaysFromItsStartDate(): void
    {
        $progress = $this->calculate($this->weeklyGoal(startDate: '2026-09-30'), [], today: '2026-09-30');

        self::assertCount(2, $progress->periods);
        self::assertSame('2026-09-30', $progress->periods[0]->start->format('Y-m-d'));
        self::assertSame('2026-10-06', $progress->periods[0]->end->format('Y-m-d'));
        self::assertSame('2026-10-07', $progress->periods[1]->start->format('Y-m-d'));
        self::assertSame('2026-10-13', $progress->periods[1]->end->format('Y-m-d'));
    }

    public function testADailyGoalHasOnePeriodPerDayFromItsStartDate(): void
    {
        $goal = $this->goal(RegularityGoalPeriodEnum::DAY, sessions: 1, duration: RegularityGoalDurationEnum::ONE_WEEK, startDate: '2026-10-07');

        $progress = $this->calculate($goal, [], today: '2026-10-07');

        self::assertCount(7, $progress->periods);
        self::assertSame('2026-10-07', $progress->periods[0]->start->format('Y-m-d'));
        self::assertSame('2026-10-13', $progress->periods[6]->start->format('Y-m-d'));
    }

    public function testTwoSessionsTheSameDayCountTwice(): void
    {
        $progress = $this->calculate($this->weeklyGoal(), ['2026-10-05 08:00', '2026-10-05 18:00', '2026-10-06 18:00'], today: '2026-10-07');

        self::assertSame(3, $progress->periods[0]->sessionCount);
        self::assertSame(RegularityGoalPeriodStatusEnum::MET, $progress->periods[0]->status);
    }

    public function testPeriodsArePastCurrentOrUpcoming(): void
    {
        $progress = $this->calculate($this->weeklyGoal(duration: RegularityGoalDurationEnum::THREE_WEEKS), ['2026-10-05 08:00', '2026-10-13 08:00'], today: '2026-10-14');

        self::assertSame(RegularityGoalPeriodStatusEnum::MISSED, $progress->periods[0]->status);
        self::assertSame(RegularityGoalPeriodStatusEnum::IN_PROGRESS, $progress->periods[1]->status);
        self::assertSame(RegularityGoalPeriodStatusEnum::UPCOMING, $progress->periods[2]->status);
    }

    /**
     * Une période manquée ne met pas fin au défi : les suivantes restent jouables.
     */
    public function testAMissedPeriodDoesNotStopTheGoal(): void
    {
        $progress = $this->calculate($this->weeklyGoal(), ['2026-10-12 08:00', '2026-10-13 08:00', '2026-10-14 08:00'], today: '2026-10-19');

        self::assertSame(RegularityGoalPeriodStatusEnum::MISSED, $progress->periods[0]->status);
        self::assertSame(RegularityGoalPeriodStatusEnum::MET, $progress->periods[1]->status);
        self::assertSame(1, $progress->metCount);
        self::assertSame(2, $progress->requiredCount);
        self::assertTrue($progress->isFinished);
        self::assertFalse($progress->isAchieved);
    }

    /**
     * Le dernier quota atteint suffit, sans attendre la fin de la dernière semaine : c'est ce qui
     * déclenchera la célébration au moment même où la séance est enregistrée.
     */
    public function testTheGoalIsAchievedAsSoonAsTheLastRequiredPeriodIsMet(): void
    {
        $sessions = ['2026-10-05 08:00', '2026-10-06 08:00', '2026-10-07 08:00', '2026-10-12 08:00', '2026-10-13 08:00', '2026-10-14 08:00'];

        $progress = $this->calculate($this->weeklyGoal(), $sessions, today: '2026-10-14');

        self::assertFalse($progress->isFinished);
        self::assertTrue($progress->isAchieved);
        self::assertSame(2, $progress->metCount);
    }

    public function testAGoalWhoseRemainingPeriodsAreUpcomingIsNotAchievedYet(): void
    {
        $progress = $this->calculate($this->weeklyGoal(), ['2026-10-05 08:00', '2026-10-06 08:00', '2026-10-07 08:00'], today: '2026-10-08');

        self::assertFalse($progress->isAchieved);
        self::assertSame(1, $progress->metCount);
    }

    public function testSessionsOutsideTheGoalAreIgnored(): void
    {
        $progress = $this->calculate($this->weeklyGoal(), ['2026-10-04 23:00', '2026-10-19 08:00'], today: '2026-10-20');

        self::assertSame(0, $progress->periods[0]->sessionCount);
        self::assertSame(0, $progress->periods[1]->sessionCount);
    }

    private function weeklyGoal(
        string $startDate = '2026-10-05',
        RegularityGoalDurationEnum $duration = RegularityGoalDurationEnum::TWO_WEEKS,
    ): RegularityGoal {
        return $this->goal(RegularityGoalPeriodEnum::WEEK, sessions: 3, duration: $duration, startDate: $startDate);
    }

    private function goal(RegularityGoalPeriodEnum $period, int $sessions, RegularityGoalDurationEnum $duration, string $startDate): RegularityGoal
    {
        $goal = new RegularityGoal();
        $goal->period = $period;
        $goal->sessionsPerPeriod = $sessions;
        $goal->duration = $duration;
        $goal->startDate = new \DateTimeImmutable($startDate);

        return $goal;
    }

    /**
     * @param list<string> $sessions
     */
    private function calculate(RegularityGoal $goal, array $sessions, string $today): RegularityGoalProgress
    {
        return (new RegularityGoalProgressCalculator())->calculate(
            $goal,
            array_map(static fn (string $session): \DateTimeImmutable => new \DateTimeImmutable($session), $sessions),
            new \DateTimeImmutable($today),
        );
    }
}
