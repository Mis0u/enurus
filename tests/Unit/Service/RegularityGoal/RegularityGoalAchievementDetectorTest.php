<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\RegularityGoal;

use App\Entity\RegularityGoal;
use App\Entity\User;
use App\Enum\Entity\RegularityGoal\RegularityGoalDurationEnum;
use App\Enum\Entity\RegularityGoal\RegularityGoalPeriodEnum;
use App\Repository\RegularityGoalRepository;
use App\Repository\WorkoutStatsRepository;
use App\Service\RegularityGoal\RegularityGoalAchievementDetector;
use App\Service\RegularityGoal\RegularityGoalProgressCalculator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RegularityGoalAchievementDetectorTest extends TestCase
{
    public function testAGoalCompletedByTheLatestWorkoutIsReportedAndMarkedAchieved(): void
    {
        $goal = $this->oneSessionThisWeekGoal();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $achieved = $this->detector([$goal], ['today 09:00'], $entityManager)->detectNewlyAchieved(new User());

        self::assertSame([$goal], $achieved);
        self::assertNotNull($goal->achievedAt);
    }

    /**
     * Célébré une seule fois : modifier ensuite la séance qui l'a complété ne le rejoue pas.
     */
    public function testAGoalAlreadyCelebratedIsNeverReportedAgain(): void
    {
        $goal = $this->oneSessionThisWeekGoal();
        $goal->achievedAt = new \DateTimeImmutable('-1 hour');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        self::assertSame([], $this->detector([$goal], ['today 09:00'], $entityManager)->detectNewlyAchieved(new User()));
    }

    public function testAGoalNotAchievedYetIsNotReported(): void
    {
        $goal = $this->oneSessionThisWeekGoal();
        $goal->sessionsPerPeriod = 3;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        self::assertSame([], $this->detector([$goal], ['today 09:00'], $entityManager)->detectNewlyAchieved(new User()));
        self::assertNull($goal->achievedAt);
    }

    private function oneSessionThisWeekGoal(): RegularityGoal
    {
        $goal = new RegularityGoal();
        $goal->period = RegularityGoalPeriodEnum::WEEK;
        $goal->sessionsPerPeriod = 1;
        $goal->duration = RegularityGoalDurationEnum::ONE_WEEK;
        $goal->startDate = new \DateTimeImmutable('today');

        return $goal;
    }

    /**
     * @param list<RegularityGoal> $goals
     * @param list<string>         $workoutDates
     */
    private function detector(array $goals, array $workoutDates, EntityManagerInterface $entityManager): RegularityGoalAchievementDetector
    {
        $goalRepository = $this->createStub(RegularityGoalRepository::class);
        $goalRepository->method('findByOwnerNewestFirst')->willReturn($goals);
        $statsRepository = $this->createStub(WorkoutStatsRepository::class);
        $statsRepository->method('findAllPerformedDatesByUser')->willReturn(
            array_map(static fn (string $date): \DateTimeImmutable => new \DateTimeImmutable($date), $workoutDates),
        );

        return new RegularityGoalAchievementDetector($goalRepository, $statsRepository, new RegularityGoalProgressCalculator(), $entityManager);
    }
}
