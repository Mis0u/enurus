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
use App\Repository\WorkoutRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WorkoutRepositoryTest extends KernelTestCase
{
    /**
     * Verrouille le comportement de `findLatestByUser()` avant refactoring anti-cartésien
     * (TODO #25) : le workout retourné doit avoir `workoutExercises` (dans l'ordre de position),
     * `exercise->exerciseMuscles->muscleGroup` et `exerciseSets` (dans l'ordre de position)
     * tous correctement peuplés, quel que soit le nombre de requêtes utilisées en interne.
     */
    public function testFindLatestByUserEagerLoadsExercisesSetsAndMuscles(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        /** @var MuscleGroupRepository $muscleGroupRepository */
        $muscleGroupRepository = static::getContainer()->get(MuscleGroupRepository::class);

        $muscleGroups = $muscleGroupRepository->findAllOrderedByPosition();
        self::assertGreaterThanOrEqual(2, \count($muscleGroups), 'Test requires at least 2 seeded muscle groups.');
        [$muscleA, $muscleB] = $muscleGroups;

        $user = new User();
        $user->email = 'find-latest-test@test.com';
        $user->password = 'hashed';
        $user->nickname = 'FindLatestTest';
        $user->locale = 'fr';
        $user->lastLogin = new \DateTimeImmutable();
        $em->persist($user);

        $exercise1 = $this->createExercise($em, 'Exercise A', $muscleA, $muscleB);
        $exercise2 = $this->createExercise($em, 'Exercise B', $muscleB, $muscleA);

        $olderWorkout = new Workout();
        $olderWorkout->owner = $user;
        $olderWorkout->performedAt = new \DateTimeImmutable('-10 days');
        $em->persist($olderWorkout);

        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = new \DateTimeImmutable('-1 day');

        $we1 = new WorkoutExercise();
        $we1->exercise = $exercise1;
        $we1->position = 0;
        $workout->addWorkoutExercise($we1);
        $this->addSets($we1, [20.0, 22.5]);

        $we2 = new WorkoutExercise();
        $we2->exercise = $exercise2;
        $we2->position = 1;
        $workout->addWorkoutExercise($we2);
        $this->addSets($we2, [30.0]);

        $em->persist($workout);
        $em->flush();
        $em->clear();

        $result = $workoutRepository->findLatestByUser($user);

        self::assertNotNull($result);
        self::assertTrue($workout->id?->equals($result->id));

        $exercises = array_values($result->workoutExercises->toArray());
        self::assertCount(2, $exercises);
        self::assertStringStartsWith('Exercise A', $exercises[0]->exercise->name, 'workoutExercises doit rester trié par position ASC.');
        self::assertStringStartsWith('Exercise B', $exercises[1]->exercise->name);

        self::assertCount(2, $exercises[0]->exerciseSets);
        self::assertCount(1, $exercises[1]->exerciseSets);

        $weights = array_map(static fn (ExerciseSet $set): float => $set->weight, $exercises[0]->exerciseSets->toArray());
        self::assertSame([20.0, 22.5], $weights, 'exerciseSets doit rester trié par position.');

        self::assertCount(2, $exercises[0]->exercise->exerciseMuscles);
        $muscleNames = array_map(
            static fn (ExerciseMuscle $em): string => $em->muscleGroup->name,
            $exercises[0]->exercise->exerciseMuscles->toArray(),
        );
        self::assertContains($muscleA->name, $muscleNames);
        self::assertContains($muscleB->name, $muscleNames);
    }

    private function createExercise(EntityManagerInterface $em, string $name, MuscleGroup $primary, MuscleGroup $secondary): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = $name . ' ' . uniqid();
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

        return $exercise;
    }

    /**
     * @param float[] $weights
     */
    private function addSets(WorkoutExercise $workoutExercise, array $weights): void
    {
        foreach ($weights as $position => $weight) {
            $set = new ExerciseSet();
            $set->position = $position;
            $set->weight = $weight;
            $set->reps = 10;
            $workoutExercise->addExerciseSet($set);
        }
    }
}
