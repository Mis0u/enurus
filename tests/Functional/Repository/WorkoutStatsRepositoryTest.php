<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Repository\WorkoutStatsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WorkoutStatsRepositoryTest extends KernelTestCase
{
    public function testFindIdsByUserAndDateRangeOnlyReturnsWorkoutsWithinRangeForTheOwner(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkoutStatsRepository $workoutStatsRepository */
        $workoutStatsRepository = static::getContainer()->get(WorkoutStatsRepository::class);

        $user = $this->createTestUser($em);
        $otherUser = $this->createTestUser($em);
        $exercise = $this->createTestExercise($em);

        $inRange = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-2 days'), 1, 5);
        $outOfRange = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-90 days'), 1, 5);
        $otherUserWorkout = $this->createTestWorkout($em, $otherUser, $exercise, new \DateTimeImmutable('-2 days'), 1, 5);

        $ids = $workoutStatsRepository->findIdsByUserAndDateRange(
            $user,
            new \DateTimeImmutable('-10 days'),
            new \DateTimeImmutable('now'),
        );

        self::assertSame([(string) $inRange->id], $ids);

        $em->remove($inRange);
        $em->remove($outOfRange);
        $em->remove($otherUserWorkout);
        $em->remove($exercise);
        $em->remove($user);
        $em->remove($otherUser);
        $em->flush();
    }

    public function testFindAllPerformedDatesByUserReturnsOneEntryPerWorkout(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkoutStatsRepository $workoutStatsRepository */
        $workoutStatsRepository = static::getContainer()->get(WorkoutStatsRepository::class);

        $user = $this->createTestUser($em);
        $exercise = $this->createTestExercise($em);

        $workoutOne = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-2 days'), 1, 5);
        $workoutTwo = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-1 day'), 1, 5);

        $dates = $workoutStatsRepository->findAllPerformedDatesByUser($user);

        self::assertCount(2, $dates);

        $em->remove($workoutOne);
        $em->remove($workoutTwo);
        $em->remove($exercise);
        $em->remove($user);
        $em->flush();
    }

    public function testCountByUserAndDateCountsOnlyWorkoutsWithinTheRange(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkoutStatsRepository $workoutStatsRepository */
        $workoutStatsRepository = static::getContainer()->get(WorkoutStatsRepository::class);

        $user = $this->createTestUser($em);
        $exercise = $this->createTestExercise($em);

        $inRange = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-2 days'), 1, 5);
        $outOfRange = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-90 days'), 1, 5);

        $count = $workoutStatsRepository->countByUserAndDate(
            $user,
            new \DateTimeImmutable('-10 days'),
            new \DateTimeImmutable('now'),
        );

        self::assertSame(1, $count);

        $em->remove($inRange);
        $em->remove($outOfRange);
        $em->remove($exercise);
        $em->remove($user);
        $em->flush();
    }

    public function testFindExerciseSetRepTotalsAggregatesAcrossAllSets(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkoutStatsRepository $workoutStatsRepository */
        $workoutStatsRepository = static::getContainer()->get(WorkoutStatsRepository::class);

        $user = $this->createTestUser($em);
        $exercise = $this->createTestExercise($em);

        $workout = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-1 day'), setCount: 3, repsPerSet: 8);

        $totals = $workoutStatsRepository->findExerciseSetRepTotals($user);

        self::assertSame(1, $totals['exercises']);
        self::assertSame(3, $totals['sets']);
        self::assertSame(24, $totals['reps']);

        $em->remove($workout);
        $em->remove($exercise);
        $em->remove($user);
        $em->flush();
    }

    public function testFindExerciseCountByWorkoutIdsReturnsExerciseCountPerWorkout(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkoutStatsRepository $workoutStatsRepository */
        $workoutStatsRepository = static::getContainer()->get(WorkoutStatsRepository::class);

        $user = $this->createTestUser($em);
        $exercise = $this->createTestExercise($em);

        $workout = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-1 day'), setCount: 1, repsPerSet: 5);

        $secondWorkoutExercise = new WorkoutExercise();
        $secondWorkoutExercise->exercise = $exercise;
        $secondWorkoutExercise->position = 1;
        $secondSet = new ExerciseSet();
        $secondSet->position = 0;
        $secondSet->weight = 20.0;
        $secondSet->reps = 5;
        $secondWorkoutExercise->addExerciseSet($secondSet);
        $workout->addWorkoutExercise($secondWorkoutExercise);
        $em->flush();

        $counts = $workoutStatsRepository->findExerciseCountByWorkoutIds([(string) $workout->id]);

        self::assertSame(2, $counts[(string) $workout->id]);

        $em->remove($workout);
        $em->remove($exercise);
        $em->remove($user);
        $em->flush();
    }

    private function createTestUser(EntityManagerInterface $em): User
    {
        $user = new User();
        $user->email = \sprintf('stats-repository-test-%s@test.com', uniqid());
        $user->password = 'hashed';
        $user->nickname = 'StatsTestUser';
        $user->locale = 'fr';
        $user->lastLogin = new \DateTimeImmutable();

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createTestExercise(EntityManagerInterface $em): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Stats test exercise ' . uniqid();
        $exercise->isPublic = true;

        $em->persist($exercise);
        $em->flush();

        return $exercise;
    }

    private function createTestWorkout(
        EntityManagerInterface $em,
        User $user,
        Exercise $exercise,
        \DateTimeImmutable $performedAt,
        int $setCount,
        int $repsPerSet,
    ): Workout {
        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = $performedAt;

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = 0;
        $workout->addWorkoutExercise($workoutExercise);

        for ($i = 0; $i < $setCount; ++$i) {
            $set = new ExerciseSet();
            $set->position = $i;
            $set->weight = 20.0;
            $set->reps = $repsPerSet;
            $workoutExercise->addExerciseSet($set);
        }

        $em->persist($workout);
        $em->flush();

        return $workout;
    }
}
