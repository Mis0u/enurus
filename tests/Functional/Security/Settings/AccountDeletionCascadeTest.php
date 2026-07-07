<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Entity\Exercise;
use App\Entity\Routine;
use App\Entity\User;
use App\Entity\Workout;
use App\Repository\ExerciseRepository;
use App\Repository\RoutineRepository;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Service\Entity\AccountDeletionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountDeletionCascadeTest extends KernelTestCase
{
    public function testDeletingAccountCascadesToAllOwnedDataButPreservesPublicExercise(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->email = 'cascade-test@test.com';
        $user->password = 'hashed';
        $user->nickname = 'CascadeTest';
        $user->lastLogin = new \DateTimeImmutable();
        $user->deletionRequestedAt = new \DateTimeImmutable('-35 days');
        $em->persist($user);

        $customExercise = new Exercise();
        $customExercise->name = 'Custom cascade exercise';
        $customExercise->isPublic = false;
        $customExercise->owner = $user;
        $em->persist($customExercise);

        $routine = new Routine();
        $routine->owner = $user;
        $routine->name = 'Cascade routine';
        $em->persist($routine);

        $workout = new Workout();
        $workout->owner = $user;
        $em->persist($workout);

        $em->flush();

        $customExerciseId = $customExercise->id;
        $routineId = $routine->id;
        $workoutId = $workout->id;

        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);
        /** @var Exercise|null $publicExercise */
        $publicExercise = $exerciseRepository->findOneBy([
            'isPublic' => true,
        ]);
        self::assertNotNull($publicExercise, 'Un exercice public doit exister en fixtures pour ce test');
        $publicExerciseId = $publicExercise->id;

        $em->clear();

        /** @var AccountDeletionService $accountDeletionService */
        $accountDeletionService = static::getContainer()->get(AccountDeletionService::class);
        $accountDeletionService->purgeExpired();

        $em->clear();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        self::assertNull($userRepository->findOneBy([
            'email' => 'cascade-test@test.com',
        ]));

        self::assertNull($exerciseRepository->find($customExerciseId));

        /** @var RoutineRepository $routineRepository */
        $routineRepository = static::getContainer()->get(RoutineRepository::class);
        self::assertNull($routineRepository->find($routineId));

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        self::assertNull($workoutRepository->find($workoutId));

        self::assertNotNull($exerciseRepository->find($publicExerciseId), "L'exercice public ne doit jamais être supprimé");
    }
}
