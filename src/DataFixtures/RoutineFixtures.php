<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Exercise;
use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class RoutineFixtures extends Fixture implements DependentFixtureInterface
{
    public const string ROUTINE_PUSH_DAY = 'routine-push-day';

    public const string ROUTINE_OTHER_USER = 'routine-other-user';

    public function load(ObjectManager $manager): void
    {
        /** @var User $owner */
        $owner = $this->getReference(
            \sprintf('%s%s', UserFixtures::REFERENCE_PREFIX, UserFixtures::USER_ROUTINE_OWNER),
            User::class,
        );

        /** @var User $otherUser */
        $otherUser = $this->getReference(
            \sprintf('%s%s', UserFixtures::REFERENCE_PREFIX, UserFixtures::USER_ROUTINE_OTHER),
            User::class,
        );

        /** @var Exercise $exercise */
        $exercise = $this->getReference(
            \sprintf('%s%d', ExerciseFixtures::REFERENCE_PREFIX, 0),
            Exercise::class,
        );

        $routine = new Routine();
        $routine->owner = $owner;
        $routine->name = 'Push Day';

        $routineExercise = new RoutineExercise();
        $routineExercise->exercise = $exercise;
        $routineExercise->position = 1;
        $routineExercise->routine = $routine;
        $routine->routineExercises->add($routineExercise);

        $manager->persist($routine);
        $this->addReference(self::ROUTINE_PUSH_DAY, $routine);

        // Routine appartenant à un autre user — pour tester l'isolation
        $otherRoutine = new Routine();
        $otherRoutine->owner = $otherUser;
        $otherRoutine->name = 'Other User Routine';

        $otherRoutineExercise = new RoutineExercise();
        $otherRoutineExercise->exercise = $exercise;
        $otherRoutineExercise->position = 1;
        $otherRoutineExercise->routine = $otherRoutine;
        $otherRoutine->routineExercises->add($otherRoutineExercise);

        $manager->persist($otherRoutine);
        $this->addReference(self::ROUTINE_OTHER_USER, $otherRoutine);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            ExerciseFixtures::class,
        ];
    }
}
