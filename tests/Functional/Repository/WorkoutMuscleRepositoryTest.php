<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Entity\ExerciseSet;
use App\Entity\MuscleGroup;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Repository\MuscleGroupRepository;
use App\Repository\WorkoutMuscleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WorkoutMuscleRepositoryTest extends KernelTestCase
{
    public function testFindLastSolicitationDatesByMuscleGroupReturnsMostRecentDatePerMuscle(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkoutMuscleRepository $workoutMuscleRepository */
        $workoutMuscleRepository = static::getContainer()->get(WorkoutMuscleRepository::class);
        /** @var MuscleGroupRepository $muscleGroupRepository */
        $muscleGroupRepository = static::getContainer()->get(MuscleGroupRepository::class);

        $muscleGroups = $muscleGroupRepository->findAllOrderedByPosition();
        self::assertGreaterThanOrEqual(2, \count($muscleGroups), 'Test requires at least 2 seeded muscle groups.');
        [$muscleA, $muscleB] = $muscleGroups;

        $user = $this->createTestUser($em, 'last-solicited-test@test.com');
        $exercise = $this->createTestExercise($em, $muscleA, $muscleB);

        $oldWorkout = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-30 days'));
        $recentWorkout = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-2 days'));

        $result = $workoutMuscleRepository->findLastSolicitationDatesByMuscleGroup($user);

        $muscleAId = (string) $muscleA->id;
        $muscleBId = (string) $muscleB->id;

        self::assertArrayHasKey($muscleAId, $result);
        self::assertArrayHasKey($muscleBId, $result);
        self::assertSame($recentWorkout->performedAt->format('Y-m-d H:i:s'), $result[$muscleAId]->format('Y-m-d H:i:s'));
        self::assertSame($recentWorkout->performedAt->format('Y-m-d H:i:s'), $result[$muscleBId]->format('Y-m-d H:i:s'));

        $em->remove($oldWorkout);
        $em->remove($recentWorkout);
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

    private function createTestExercise(EntityManagerInterface $em, MuscleGroup $primary, MuscleGroup $secondary): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Test exercise ' . uniqid();
        $exercise->isPublic = true;

        $primaryMuscle = new ExerciseMuscle();
        $primaryMuscle->exercise = $exercise;
        $primaryMuscle->muscleGroup = $primary;
        $primaryMuscle->type = MuscleTypeEnum::PRIMARY;
        $exercise->exerciseMuscles->add($primaryMuscle);

        $secondaryMuscle = new ExerciseMuscle();
        $secondaryMuscle->exercise = $exercise;
        $secondaryMuscle->muscleGroup = $secondary;
        $secondaryMuscle->type = MuscleTypeEnum::SECONDARY;
        $exercise->exerciseMuscles->add($secondaryMuscle);

        $em->persist($exercise);
        $em->persist($primaryMuscle);
        $em->persist($secondaryMuscle);
        $em->flush();

        return $exercise;
    }

    private function createTestWorkout(EntityManagerInterface $em, User $user, Exercise $exercise, \DateTimeImmutable $performedAt): Workout
    {
        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = $performedAt;

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = 0;
        $workout->addWorkoutExercise($workoutExercise);

        $set = new ExerciseSet();
        $set->position = 0;
        $set->weight = 20.0;
        $set->reps = 10;
        $workoutExercise->addExerciseSet($set);

        $em->persist($workout);
        $em->persist($workoutExercise);
        $em->persist($set);
        $em->flush();

        return $workout;
    }
}
