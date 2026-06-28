<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Entity\MuscleGroup;
use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Entity\User;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class RoutineFixtures extends Fixture implements DependentFixtureInterface
{
    public const string EXERCISE_OTHER_USER = 'routine-other-user-exercise';

    public const string ROUTINE_OTHER_USER = 'routine-other-user';

    public const string ROUTINE_PUSH_DAY = 'routine-push-day';

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
        $this->loadOtherUserExercise($manager, $otherUser);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            ExerciseFixtures::class,
        ];
    }

    private function loadOtherUserExercise(ObjectManager $manager, User $otherUser): void
    {
        /** @var MuscleGroup $muscleGroup */
        $muscleGroup = $this->getReference(
            \sprintf('%s%s', MuscleGroupFixtures::REFERENCE_PREFIX, 'name.chest'),
            MuscleGroup::class,
        );

        $exercise = new Exercise();
        $exercise->name = self::EXERCISE_OTHER_USER;
        $exercise->isPublic = false;
        $exercise->owner = $otherUser;

        $exerciseMuscle = new ExerciseMuscle();
        $exerciseMuscle->exercise = $exercise;
        $exerciseMuscle->muscleGroup = $muscleGroup;
        $exerciseMuscle->type = MuscleTypeEnum::PRIMARY;
        $exercise->exerciseMuscles->add($exerciseMuscle);

        $manager->persist($exercise);
        $this->addReference(self::EXERCISE_OTHER_USER, $exercise);
    }
}
