<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Repository\UserRepository;
use App\Repository\WorkoutExportRepository;
use App\Repository\WorkoutRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WorkoutExportRepositoryTest extends KernelTestCase
{
    // 11 séances : plusieurs lots de 2, dont un dernier incomplet.
    private const string USER_EMAIL = 'user-fixture-11-workout@test.com';

    private const int BATCH_SIZE = 2;

    public function testEveryWorkoutOfTheUserIsReadOnceInChronologicalOrder(): void
    {
        self::bootKernel();
        $user = $this->user();
        $expectedCount = $this->workoutRepository()->countByUser($user);

        $performedAts = [];
        $ids = [];
        foreach ($this->exportRepository()->iterateByUser($user) as $workout) {
            $performedAts[] = $workout->performedAt;
            $ids[] = (string) $workout->id;
        }

        self::assertCount($expectedCount, $ids);
        self::assertCount($expectedCount, array_unique($ids));
        $sortedPerformedAts = $performedAts;
        sort($sortedPerformedAts);
        self::assertEquals($sortedPerformedAts, $performedAts);
    }

    /**
     * La mémoire ne doit pas grossir avec l'historique : jamais plus d'un lot de séances gardé par
     * Doctrine à la fois.
     */
    public function testNoMoreThanOneBatchOfWorkoutsIsKeptInMemory(): void
    {
        self::bootKernel();
        $unitOfWork = $this->entityManager()->getUnitOfWork();

        $maxManagedWorkouts = 0;
        foreach ($this->exportRepository()->iterateByUser($this->user()) as $ignored) {
            $maxManagedWorkouts = max($maxManagedWorkouts, \count($unitOfWork->getIdentityMap()[Workout::class] ?? []));
        }

        self::assertSame(self::BATCH_SIZE, $maxManagedWorkouts);
    }

    public function testExercisesAndSetsComeLoadedInTheirPositionOrder(): void
    {
        self::bootKernel();

        foreach ($this->exportRepository()->iterateByUser($this->user()) as $workout) {
            $positions = array_map(static fn (WorkoutExercise $workoutExercise): int => $workoutExercise->position, $workout->workoutExercises->toArray());
            $sortedPositions = $positions;
            sort($sortedPositions);
            self::assertSame($sortedPositions, array_values($positions));
        }
    }

    private function exportRepository(): WorkoutExportRepository
    {
        return new WorkoutExportRepository($this->workoutRepository(), $this->entityManager(), self::BATCH_SIZE);
    }

    private function workoutRepository(): WorkoutRepository
    {
        /** @var WorkoutRepository */
        return static::getContainer()->get(WorkoutRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function user(): User
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        return $userRepository->findOneBy([
            'email' => self::USER_EMAIL,
        ]) ?? throw new \LogicException('Fixture user not found.');
    }
}
