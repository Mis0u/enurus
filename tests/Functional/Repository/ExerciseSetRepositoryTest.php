<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
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

    private function createTestExercise(EntityManagerInterface $em): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Test exercise ' . uniqid();
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
}
