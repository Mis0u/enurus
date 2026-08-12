<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\WorkoutTonnageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WorkoutTonnageRepositoryTest extends KernelTestCase
{
    public function testFindTonnageByWorkoutIdsReturnsEmptyArrayForEmptyInput(): void
    {
        self::bootKernel();

        /** @var WorkoutTonnageRepository $workoutTonnageRepository */
        $workoutTonnageRepository = static::getContainer()->get(WorkoutTonnageRepository::class);

        self::assertSame([], $workoutTonnageRepository->findTonnageByWorkoutIds([]));
    }

    public function testFindTonnageByWorkoutIdsSumsWeightTimesRepsAcrossAllSets(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkoutTonnageRepository $workoutTonnageRepository */
        $workoutTonnageRepository = static::getContainer()->get(WorkoutTonnageRepository::class);

        $user = $this->createTestUser($em);
        $exercise = $this->createTestExercise($em);
        $workout = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-1 day'), [
            [
                'weight' => 100.0,
                'reps' => 5,
            ], // 500
            [
                'weight' => 50.0,
                'reps' => 10,
            ], // 500
        ]);

        $result = $workoutTonnageRepository->findTonnageByWorkoutIds([(string) $workout->id]);

        self::assertSame(1000.0, $result[(string) $workout->id]);

        $em->remove($workout);
        $em->remove($exercise);
        $em->remove($user);
        $em->flush();
    }

    public function testFindTonnageSeriesByUserOnlyIncludesWorkoutsWithinTheDateRange(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkoutTonnageRepository $workoutTonnageRepository */
        $workoutTonnageRepository = static::getContainer()->get(WorkoutTonnageRepository::class);

        $user = $this->createTestUser($em);
        $exercise = $this->createTestExercise($em);

        $inRange = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-5 days'), [
            [
                'weight' => 20.0,
                'reps' => 10,
            ], // 200
        ]);
        $outOfRange = $this->createTestWorkout($em, $user, $exercise, new \DateTimeImmutable('-90 days'), [
            [
                'weight' => 999.0,
                'reps' => 1,
            ],
        ]);

        $series = $workoutTonnageRepository->findTonnageSeriesByUser(
            $user,
            new \DateTimeImmutable('-10 days'),
            new \DateTimeImmutable('now'),
        );

        self::assertCount(1, $series);
        self::assertSame(200.0, $series[0]['tonnage']);
        self::assertSame($inRange->performedAt->format('Y-m-d'), $series[0]['performedAt']->format('Y-m-d'));

        $em->remove($inRange);
        $em->remove($outOfRange);
        $em->remove($exercise);
        $em->remove($user);
        $em->flush();
    }

    public function testFindTonnageByWorkoutIdsCountsTimeBasedSetWeightAloneAsOneRep(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkoutTonnageRepository $workoutTonnageRepository */
        $workoutTonnageRepository = static::getContainer()->get(WorkoutTonnageRepository::class);

        $user = $this->createTestUser($em);
        $weightExercise = $this->createTestExercise($em);
        $timeExercise = $this->createTestExercise($em, MeasurementType::TIME);

        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = new \DateTimeImmutable('-1 day');

        $weightWorkoutExercise = new WorkoutExercise();
        $weightWorkoutExercise->exercise = $weightExercise;
        $weightWorkoutExercise->position = 0;
        $workout->addWorkoutExercise($weightWorkoutExercise);

        $weightSet = new ExerciseSet();
        $weightSet->position = 0;
        $weightSet->weight = 100.0;
        $weightSet->reps = 5;
        $weightWorkoutExercise->addExerciseSet($weightSet);

        $timeWorkoutExercise = new WorkoutExercise();
        $timeWorkoutExercise->exercise = $timeExercise;
        $timeWorkoutExercise->position = 1;
        $workout->addWorkoutExercise($timeWorkoutExercise);

        // Gainage lesté à 20kg : compte pour 20 dans le tonnage (poids seul, comme une série à 1
        // rep), jamais poids × durée — les unités ne seraient pas comparables au reste du total.
        $timeSet = new ExerciseSet();
        $timeSet->position = 0;
        $timeSet->weight = 20.0;
        $timeSet->duration = 480;
        $timeWorkoutExercise->addExerciseSet($timeSet);

        $em->persist($workout);
        $em->flush();

        $result = $workoutTonnageRepository->findTonnageByWorkoutIds([(string) $workout->id]);

        // 100kg × 5 (weight_reps) + 20kg (time, poids seul) = 520.
        self::assertSame(520.0, $result[(string) $workout->id]);

        $em->remove($workout);
        $em->remove($weightExercise);
        $em->remove($timeExercise);
        $em->remove($user);
        $em->flush();
    }

    public function testFindTonnageByWorkoutIdsTreatsBodyweightTimeSetAsZeroTonnage(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkoutTonnageRepository $workoutTonnageRepository */
        $workoutTonnageRepository = static::getContainer()->get(WorkoutTonnageRepository::class);

        $user = $this->createTestUser($em);
        $timeExercise = $this->createTestExercise($em, MeasurementType::TIME);

        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = new \DateTimeImmutable('-1 day');

        $timeWorkoutExercise = new WorkoutExercise();
        $timeWorkoutExercise->exercise = $timeExercise;
        $timeWorkoutExercise->position = 0;
        $workout->addWorkoutExercise($timeWorkoutExercise);

        $timeSet = new ExerciseSet();
        $timeSet->position = 0;
        $timeSet->duration = 480;
        $timeWorkoutExercise->addExerciseSet($timeSet);

        $em->persist($workout);
        $em->flush();

        $result = $workoutTonnageRepository->findTonnageByWorkoutIds([(string) $workout->id]);

        self::assertSame(0.0, $result[(string) $workout->id]);

        $em->remove($workout);
        $em->remove($timeExercise);
        $em->remove($user);
        $em->flush();
    }

    public function testFindTonnageByWorkoutIdsCountsDistanceBasedSetWeightAloneAsOneRep(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkoutTonnageRepository $workoutTonnageRepository */
        $workoutTonnageRepository = static::getContainer()->get(WorkoutTonnageRepository::class);

        $user = $this->createTestUser($em);
        $weightExercise = $this->createTestExercise($em);
        $distanceExercise = $this->createTestExercise($em, MeasurementType::DISTANCE);

        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = new \DateTimeImmutable('-1 day');

        $weightWorkoutExercise = new WorkoutExercise();
        $weightWorkoutExercise->exercise = $weightExercise;
        $weightWorkoutExercise->position = 0;
        $workout->addWorkoutExercise($weightWorkoutExercise);

        $weightSet = new ExerciseSet();
        $weightSet->position = 0;
        $weightSet->weight = 100.0;
        $weightSet->reps = 5;
        $weightWorkoutExercise->addExerciseSet($weightSet);

        $distanceWorkoutExercise = new WorkoutExercise();
        $distanceWorkoutExercise->exercise = $distanceExercise;
        $distanceWorkoutExercise->position = 1;
        $workout->addWorkoutExercise($distanceWorkoutExercise);

        // Farmer walk à 30kg sur 50m : compte pour 30 dans le tonnage (poids seul, comme une
        // série à 1 rep), jamais poids × distance — les unités ne seraient pas comparables au
        // reste du total.
        $distanceSet = new ExerciseSet();
        $distanceSet->position = 0;
        $distanceSet->weight = 30.0;
        $distanceSet->distance = 50;
        $distanceWorkoutExercise->addExerciseSet($distanceSet);

        $em->persist($workout);
        $em->flush();

        $result = $workoutTonnageRepository->findTonnageByWorkoutIds([(string) $workout->id]);

        // 100kg × 5 (weight_reps) + 30kg (distance, poids seul) = 530.
        self::assertSame(530.0, $result[(string) $workout->id]);

        $em->remove($workout);
        $em->remove($weightExercise);
        $em->remove($distanceExercise);
        $em->remove($user);
        $em->flush();
    }

    public function testFindTonnageByWorkoutIdsTreatsBodyweightDistanceSetAsZeroTonnage(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkoutTonnageRepository $workoutTonnageRepository */
        $workoutTonnageRepository = static::getContainer()->get(WorkoutTonnageRepository::class);

        $user = $this->createTestUser($em);
        $distanceExercise = $this->createTestExercise($em, MeasurementType::DISTANCE);

        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = new \DateTimeImmutable('-1 day');

        $distanceWorkoutExercise = new WorkoutExercise();
        $distanceWorkoutExercise->exercise = $distanceExercise;
        $distanceWorkoutExercise->position = 0;
        $workout->addWorkoutExercise($distanceWorkoutExercise);

        $distanceSet = new ExerciseSet();
        $distanceSet->position = 0;
        $distanceSet->distance = 50;
        $distanceWorkoutExercise->addExerciseSet($distanceSet);

        $em->persist($workout);
        $em->flush();

        $result = $workoutTonnageRepository->findTonnageByWorkoutIds([(string) $workout->id]);

        self::assertSame(0.0, $result[(string) $workout->id]);

        $em->remove($workout);
        $em->remove($distanceExercise);
        $em->remove($user);
        $em->flush();
    }

    private function createTestUser(EntityManagerInterface $em): User
    {
        $user = new User();
        $user->email = \sprintf('tonnage-repository-test-%s@test.com', uniqid());
        $user->password = 'hashed';
        $user->nickname = 'TonnageTestUser';
        $user->locale = 'fr';
        $user->lastLogin = new \DateTimeImmutable();

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createTestExercise(EntityManagerInterface $em, MeasurementType $measurementType = MeasurementType::WEIGHT_REPS): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Tonnage test exercise ' . uniqid();
        $exercise->isPublic = true;
        $exercise->measurementType = $measurementType;

        $em->persist($exercise);
        $em->flush();

        return $exercise;
    }

    /**
     * @param array<int, array{weight: float, reps: int}> $sets
     */
    private function createTestWorkout(
        EntityManagerInterface $em,
        User $user,
        Exercise $exercise,
        \DateTimeImmutable $performedAt,
        array $sets,
    ): Workout {
        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = $performedAt;

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = 0;
        $workout->addWorkoutExercise($workoutExercise);

        foreach ($sets as $position => $setData) {
            $set = new ExerciseSet();
            $set->position = $position;
            $set->weight = $setData['weight'];
            $set->reps = $setData['reps'];
            $workoutExercise->addExerciseSet($set);
        }

        $em->persist($workout);
        $em->flush();

        return $workout;
    }
}
