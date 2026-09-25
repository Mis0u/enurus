<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\ExerciseLastSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ExerciseLastSessionRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private ExerciseLastSessionRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
        /** @var ExerciseLastSessionRepository $repository */
        $repository = static::getContainer()->get(ExerciseLastSessionRepository::class);
        $this->repository = $repository;
    }

    public function testReturnsTheSetsOfTheMostRecentSessionInPositionOrder(): void
    {
        $user = $this->createUser('last-session-recent@test.com');
        $exercise = $this->createExercise();
        $this->createWorkout($user, $exercise, '2026-05-01 10:00', [[100.0, 10]]);
        $this->createWorkout($user, $exercise, '2026-06-01 10:00', [[125.0, 6], [120.0, 8]], reversedInsert: true);

        $lastSessions = $this->repository->findLastSessionByExercise($user, [$exercise]);

        $lastSession = $lastSessions[(string) $exercise->id];
        self::assertEquals(new \DateTimeImmutable('2026-06-01 10:00'), $lastSession->performedAt);
        self::assertSame([
            [
                'weight' => 125.0,
                'reps' => 6,
                'duration' => null,
                'distance' => null,
            ],
            [
                'weight' => 120.0,
                'reps' => 8,
                'duration' => null,
                'distance' => null,
            ],
        ], $lastSession->sets);
    }

    public function testIgnoresSessionsOfOtherUsers(): void
    {
        $user = $this->createUser('last-session-owner@test.com');
        $otherUser = $this->createUser('last-session-other@test.com');
        $exercise = $this->createExercise();
        $this->createWorkout($user, $exercise, '2026-05-01 10:00', [[100.0, 10]]);
        $this->createWorkout($otherUser, $exercise, '2026-06-01 10:00', [[200.0, 1]]);

        $lastSessions = $this->repository->findLastSessionByExercise($user, [$exercise]);

        self::assertSame(100.0, $lastSessions[(string) $exercise->id]->sets[0]['weight']);
    }

    public function testHasNoEntryForAnExerciseNeverPerformed(): void
    {
        $user = $this->createUser('last-session-never@test.com');
        $performed = $this->createExercise();
        $neverPerformed = $this->createExercise();
        $this->createWorkout($user, $performed, '2026-05-01 10:00', [[100.0, 10]]);

        $lastSessions = $this->repository->findLastSessionByExercise($user, [$performed, $neverPerformed]);

        self::assertArrayHasKey((string) $performed->id, $lastSessions);
        self::assertArrayNotHasKey((string) $neverPerformed->id, $lastSessions);
    }

    public function testResolvesEachExerciseToItsOwnLastSession(): void
    {
        $user = $this->createUser('last-session-many@test.com');
        $squat = $this->createExercise();
        $bench = $this->createExercise();
        $this->createWorkout($user, $squat, '2026-05-01 10:00', [[140.0, 5]]);
        $this->createWorkout($user, $bench, '2026-06-01 10:00', [[90.0, 8]]);

        $lastSessions = $this->repository->findLastSessionByExercise($user, [$squat, $bench]);

        self::assertSame(140.0, $lastSessions[(string) $squat->id]->sets[0]['weight']);
        self::assertSame(90.0, $lastSessions[(string) $bench->id]->sets[0]['weight']);
    }

    public function testKeepsTheLatestCreatedSessionWhenTwoShareTheSameDate(): void
    {
        $user = $this->createUser('last-session-tie@test.com');
        $exercise = $this->createExercise();
        $this->createWorkout($user, $exercise, '2026-06-01 10:00', [[100.0, 10]]);
        $this->createWorkout($user, $exercise, '2026-06-01 10:00', [[110.0, 8]]);

        $lastSessions = $this->repository->findLastSessionByExercise($user, [$exercise]);

        self::assertSame([[
            'weight' => 110.0,
            'reps' => 8,
            'duration' => null,
            'distance' => null,
        ]], $lastSessions[(string) $exercise->id]->sets);
    }

    public function testReturnsDurationAndDistanceForNonWeightExercises(): void
    {
        $user = $this->createUser('last-session-time@test.com');
        $plank = $this->createExercise(MeasurementType::TIME);
        $workout = $this->createWorkout($user, $plank, '2026-06-01 10:00', []);
        $this->addSet($workout->workoutExercises->first() ?: throw new \LogicException('No workout exercise'), 0, duration: 60);

        $lastSessions = $this->repository->findLastSessionByExercise($user, [$plank]);

        self::assertSame([[
            'weight' => 0.0,
            'reps' => 0,
            'duration' => 60,
            'distance' => null,
        ]], $lastSessions[(string) $plank->id]->sets);
    }

    public function testReturnsNothingForAnEmptyExerciseList(): void
    {
        $user = $this->createUser('last-session-empty@test.com');

        self::assertSame([], $this->repository->findLastSessionByExercise($user, []));
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->email = $email;
        $user->password = 'hashed';
        $user->nickname = 'LastSession' . uniqid();
        $user->locale = 'fr';
        $user->lastLogin = new \DateTimeImmutable();
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createExercise(MeasurementType $measurementType = MeasurementType::WEIGHT_REPS): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Last session exercise ' . uniqid();
        $exercise->isPublic = true;
        $exercise->measurementType = $measurementType;
        $this->em->persist($exercise);
        $this->em->flush();

        return $exercise;
    }

    /**
     * @param list<array{float, int}> $weightRepsSets poids/reps par position
     * @param bool $reversedInsert insère les séries en ordre inverse de leur position, pour
     *                             vérifier que le tri se fait sur `position`, pas sur l'insertion
     */
    private function createWorkout(User $user, Exercise $exercise, string $performedAt, array $weightRepsSets, bool $reversedInsert = false): Workout
    {
        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = new \DateTimeImmutable($performedAt);

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = 0;
        $workout->addWorkoutExercise($workoutExercise);
        $this->em->persist($workout);
        $this->em->flush();

        $positions = array_keys($weightRepsSets);
        foreach ($reversedInsert ? array_reverse($positions) : $positions as $position) {
            [$weight, $reps] = $weightRepsSets[$position];
            $this->addSet($workoutExercise, $position, $weight, $reps);
        }

        return $workout;
    }

    private function addSet(WorkoutExercise $workoutExercise, int $position, float $weight = 0.0, int $reps = 0, ?int $duration = null): void
    {
        $set = new ExerciseSet();
        $set->position = $position;
        $set->weight = $weight;
        $set->reps = $reps;
        $set->duration = $duration;
        $workoutExercise->addExerciseSet($set);
        $this->em->persist($set);
        $this->em->flush();
    }
}
