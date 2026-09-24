<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Un utilisateur ("hub") connecté (ACCEPTED) à 10 comptes dédiés, pour visualiser la liste
 * déroulante de « Mes connexions » au-delà du seuil de 5 entrées. Comptes dédiés plutôt que
 * réutilisation des utilisateurs indexés de `UserFixtures` : plusieurs tests fonctionnels
 * s'appuient sur leur état `isDiscoverable` par défaut (`false`), qu'une connexion fixture aurait
 * changé et cassé silencieusement ces tests.
 *
 * `user-connection-0` a une séance créée après la dernière visite du hub : point « nouvelle
 * séance » visible sur sa ligne quand on se connecte en hub.
 */
class ProfileConnectionFixtures extends Fixture implements DependentFixtureInterface
{
    private const string HUB_USER_EMAIL = 'user-fixture-26-workout@test.com';

    private const int OTHER_USERS_COUNT = 10;

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        /** @var User $hub */
        $hub = $this->getReference(UserFixtures::REFERENCE_PREFIX . self::HUB_USER_EMAIL, User::class);
        $this->makeDiscoverable($hub, 'HUBCDE');
        $now = new \DateTimeImmutable();

        for ($index = 0; self::OTHER_USERS_COUNT > $index; ++$index) {
            $other = $this->createUser($index);
            $this->makeDiscoverable($other, \sprintf('CNCT%02d', $index));
            $manager->persist($other);

            $connection = new ProfileConnection();
            $connection->requester = $hub;
            $connection->addressee = $other;
            $connection->status = ProfileConnectionStatusEnum::ACCEPTED;
            $connection->markSeenByBothParties($now);

            $manager->persist($connection);

            if (0 === $index) {
                $connection->markSeenBy($hub, $now->modify('-1 day'));
                $manager->persist($this->createWorkout($other));
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class, ExerciseFixtures::class, WorkoutFixtures::class];
    }

    private function createUser(int $index): User
    {
        $user = new User();
        $user->email = \sprintf('user-fixture-connection-%d@test.com', $index);
        $user->nickname = \sprintf('user-connection-%d', $index);
        $user->createdBy = $user;
        $user->lastLogin = new \DateTimeImmutable();
        $user->password = $this->passwordHasher->hashPassword($user, 'pass_1234');
        $user->isVerified = true;

        return $user;
    }

    private function createWorkout(User $owner): Workout
    {
        $workout = new Workout();
        $workout->owner = $owner;
        $workout->performedAt = new \DateTimeImmutable('today 08:00');

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->exercise = $this->findWeightRepsExercise();
        $workoutExercise->position = 0;
        $workout->addWorkoutExercise($workoutExercise);

        $set = new ExerciseSet();
        $set->position = 0;
        $set->weight = 60.0;
        $set->reps = 10;
        $workoutExercise->addExerciseSet($set);

        return $workout;
    }

    private function findWeightRepsExercise(): Exercise
    {
        for ($index = 0; $this->hasReference(ExerciseFixtures::REFERENCE_PREFIX . $index, Exercise::class); ++$index) {
            $exercise = $this->getReference(ExerciseFixtures::REFERENCE_PREFIX . $index, Exercise::class);

            if (MeasurementType::WEIGHT_REPS === $exercise->measurementType) {
                return $exercise;
            }
        }

        throw new \LogicException('No weight/reps exercise found in the fixtures.');
    }

    private function makeDiscoverable(User $user, string $shareCode): void
    {
        $user->isDiscoverable = true;
        $user->shareCode = $shareCode;
    }
}
