<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\ExerciseSetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ExerciseSetRepositoryTest extends KernelTestCase
{
    public function testExcludesTheWorkoutItselfAndAnyLaterWorkout(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ExerciseSetRepository $exerciseSetRepository */
        $exerciseSetRepository = static::getContainer()->get(ExerciseSetRepository::class);

        $user = $this->createTestUser($em, 'exercise-set-repo-test@test.com');
        $exercise = $this->createTestExercise($em);

        $olderWorkout = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-10 days'), 100.0);
        $recentWorkout = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-2 days'), 130.0);

        $result = $exerciseSetRepository->findMaxWeightPerExerciseBeforeDate(
            $user,
            [$exercise],
            $recentWorkout->performedAt,
        );

        // Le max avant la séance récente ne doit inclure que l'ancienne séance (100), jamais la
        // séance récente elle-même (130) — sinon une égalité de poids afficherait à tort un PR.
        self::assertSame(100.0, $result[(string) $exercise->id]);

        $em->remove($olderWorkout);
        $em->remove($recentWorkout);
        $em->remove($exercise);
        $em->remove($user);
        $em->flush();
    }

    public function testReturnsNoEntryForAnExerciseNeverDoneBeforeTheGivenDate(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ExerciseSetRepository $exerciseSetRepository */
        $exerciseSetRepository = static::getContainer()->get(ExerciseSetRepository::class);

        $user = $this->createTestUser($em, 'exercise-set-repo-test-2@test.com');
        $exercise = $this->createTestExercise($em);

        $onlyWorkout = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-1 day'), 60.0);

        $result = $exerciseSetRepository->findMaxWeightPerExerciseBeforeDate(
            $user,
            [$exercise],
            $onlyWorkout->performedAt,
        );

        self::assertArrayNotHasKey((string) $exercise->id, $result);

        $em->remove($onlyWorkout);
        $em->remove($exercise);
        $em->remove($user);
        $em->flush();
    }

    public function testExistsForExerciseIsFalseBeforeAnySetAndTrueAfterOne(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ExerciseSetRepository $exerciseSetRepository */
        $exerciseSetRepository = static::getContainer()->get(ExerciseSetRepository::class);

        $user = $this->createTestUser($em, 'exercise-set-repo-test-exists@test.com');
        $exercise = $this->createTestExercise($em);

        self::assertFalse($exerciseSetRepository->existsForExercise($exercise));

        $workout = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-1 day'), 60.0);

        self::assertTrue($exerciseSetRepository->existsForExercise($exercise));

        $em->remove($workout);
        $em->remove($exercise);
        $em->remove($user);
        $em->flush();
    }

    public function testFindMaxDurationPerExerciseBeforeDateExcludesTheWorkoutItself(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ExerciseSetRepository $exerciseSetRepository */
        $exerciseSetRepository = static::getContainer()->get(ExerciseSetRepository::class);

        $user = $this->createTestUser($em, 'exercise-set-repo-test-duration@test.com');
        $exercise = $this->createTestExercise($em, MeasurementType::TIME);

        $olderWorkout = $this->createTestTimeWorkout($em, $user, $exercise, new \DateTimeImmutable('-10 days'), 300);
        $recentWorkout = $this->createTestTimeWorkout($em, $user, $exercise, new \DateTimeImmutable('-2 days'), 480);

        $result = $exerciseSetRepository->findMaxDurationPerExerciseBeforeDate(
            $user,
            [$exercise],
            $recentWorkout->performedAt,
        );

        // Le max avant la séance récente ne doit inclure que l'ancienne séance (300s), jamais la
        // séance récente elle-même (480s).
        self::assertSame(300, $result[(string) $exercise->id]);

        $em->remove($olderWorkout);
        $em->remove($recentWorkout);
        $em->remove($exercise);
        $em->remove($user);
        $em->flush();
    }

    public function testFindMaxDistancePerExerciseBeforeDateExcludesTheWorkoutItself(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ExerciseSetRepository $exerciseSetRepository */
        $exerciseSetRepository = static::getContainer()->get(ExerciseSetRepository::class);

        $user = $this->createTestUser($em, 'exercise-set-repo-test-distance@test.com');
        $exercise = $this->createTestExercise($em, MeasurementType::DISTANCE);

        $olderWorkout = $this->createTestDistanceWorkout($em, $user, $exercise, new \DateTimeImmutable('-10 days'), 50);
        $recentWorkout = $this->createTestDistanceWorkout($em, $user, $exercise, new \DateTimeImmutable('-2 days'), 100);

        $result = $exerciseSetRepository->findMaxDistancePerExerciseBeforeDate(
            $user,
            [$exercise],
            $recentWorkout->performedAt,
        );

        // Le max avant la séance récente ne doit inclure que l'ancienne séance (50m), jamais la
        // séance récente elle-même (100m).
        self::assertSame(50, $result[(string) $exercise->id]);

        $em->remove($olderWorkout);
        $em->remove($recentWorkout);
        $em->remove($exercise);
        $em->remove($user);
        $em->flush();
    }

    public function testFindSessionHistoryReturnsOneRowPerSetOrderedChronologically(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ExerciseSetRepository $exerciseSetRepository */
        $exerciseSetRepository = static::getContainer()->get(ExerciseSetRepository::class);

        $user = $this->createTestUser($em, 'exercise-set-repo-test-history@test.com');
        $exercise = $this->createTestExercise($em);

        $olderWorkout = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-10 days'), 100.0);
        $recentWorkout = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-2 days'), 110.0);

        $result = $exerciseSetRepository->findSessionHistoryForExerciseAndUser($user, $exercise);

        self::assertCount(2, $result);
        self::assertSame((string) $olderWorkout->id, $result[0]['workoutId']);
        self::assertSame(100.0, $result[0]['weight']);
        self::assertSame((string) $recentWorkout->id, $result[1]['workoutId']);
        self::assertSame(110.0, $result[1]['weight']);

        $em->remove($olderWorkout);
        $em->remove($recentWorkout);
        $em->remove($exercise);
        $em->remove($user);
        $em->flush();
    }

    public function testFindSessionHistoryNeverReturnsAnotherUsersSessionsOnASharedPublicExercise(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ExerciseSetRepository $exerciseSetRepository */
        $exerciseSetRepository = static::getContainer()->get(ExerciseSetRepository::class);

        $owner = $this->createTestUser($em, 'exercise-set-repo-test-history-owner@test.com');
        $otherUser = $this->createTestUser($em, 'exercise-set-repo-test-history-other@test.com');
        $exercise = $this->createTestExercise($em);

        $ownWorkout = $this->createTestWorkout($em, $owner, $exercise, new \DateTimeImmutable('-1 day'), 100.0);
        $otherWorkout = $this->createTestWorkout($em, $otherUser, $exercise, new \DateTimeImmutable('-1 day'), 999.0);

        $result = $exerciseSetRepository->findSessionHistoryForExerciseAndUser($owner, $exercise);

        self::assertCount(1, $result);
        self::assertSame((string) $ownWorkout->id, $result[0]['workoutId']);

        $em->remove($ownWorkout);
        $em->remove($otherWorkout);
        $em->remove($exercise);
        $em->remove($owner);
        $em->remove($otherUser);
        $em->flush();
    }

    private function createTestUser(EntityManagerInterface $em, string $email): User
    {
        $user = new User();
        $user->email = $email;
        $user->password = 'hashed';
        $user->nickname = 'TestUser';
        $user->locale = 'fr';
        $user->lastLogin = new \DateTimeImmutable();

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createTestExercise(EntityManagerInterface $em, MeasurementType $measurementType = MeasurementType::WEIGHT_REPS): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Test exercise ' . uniqid();
        $exercise->isPublic = true;
        $exercise->measurementType = $measurementType;

        $em->persist($exercise);
        $em->flush();

        return $exercise;
    }

    private function createTestWorkout(
        EntityManagerInterface $em,
        User $user,
        Exercise $exercise,
        \DateTimeImmutable $performedAt,
        float $weight,
    ): Workout {
        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = $performedAt;

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = 0;
        $workout->addWorkoutExercise($workoutExercise);

        $set = new ExerciseSet();
        $set->position = 0;
        $set->weight = $weight;
        $set->reps = 10;
        $workoutExercise->addExerciseSet($set);

        $em->persist($workout);
        $em->persist($workoutExercise);
        $em->persist($set);
        $em->flush();

        return $workout;
    }

    private function createTestTimeWorkout(
        EntityManagerInterface $em,
        User $user,
        Exercise $exercise,
        \DateTimeImmutable $performedAt,
        int $duration,
    ): Workout {
        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = $performedAt;

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = 0;
        $workout->addWorkoutExercise($workoutExercise);

        $set = new ExerciseSet();
        $set->position = 0;
        $set->duration = $duration;
        $workoutExercise->addExerciseSet($set);

        $em->persist($workout);
        $em->persist($workoutExercise);
        $em->persist($set);
        $em->flush();

        return $workout;
    }

    private function createTestDistanceWorkout(
        EntityManagerInterface $em,
        User $user,
        Exercise $exercise,
        \DateTimeImmutable $performedAt,
        int $distance,
    ): Workout {
        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = $performedAt;

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = 0;
        $workout->addWorkoutExercise($workoutExercise);

        $set = new ExerciseSet();
        $set->position = 0;
        $set->distance = $distance;
        $workoutExercise->addExerciseSet($set);

        $em->persist($workout);
        $em->persist($workoutExercise);
        $em->persist($set);
        $em->flush();

        return $workout;
    }
}
