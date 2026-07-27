<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\Exercise;
use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Service\Entity\RoutineEditService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RoutineEditServiceTest extends TestCase
{
    public function testUpdateReplacesOldExercisesWithNewOnesInTwoFlushes(): void
    {
        $routine = new Routine();
        $routine->name = 'Push day';

        $oldOne = $this->createRoutineExercise('Bench press');
        $oldTwo = $this->createRoutineExercise('Overhead press');
        $routine->routineExercises->add($oldOne);
        $routine->routineExercises->add($oldTwo);

        $newExercise = $this->createRoutineExercise('Incline press');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('remove');
        $em->expects(self::once())->method('persist')->with($newExercise);
        // La stratégie documentée est suppression puis réinsertion en 2 flush distincts.
        $em->expects(self::exactly(2))->method('flush');

        $service = new RoutineEditService($em);
        $service->update($routine, new ArrayCollection([$newExercise]));

        self::assertCount(1, $routine->routineExercises);
        self::assertSame($newExercise, $routine->routineExercises->first());
        self::assertSame($routine, $newExercise->routine);
    }

    public function testUpdateWithEmptyCollectionRemovesAllExistingExercises(): void
    {
        $routine = new Routine();
        $routine->name = 'Push day';

        $oldOne = $this->createRoutineExercise('Bench press');
        $routine->routineExercises->add($oldOne);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($oldOne);
        $em->expects(self::never())->method('persist');
        $em->expects(self::exactly(2))->method('flush');

        $service = new RoutineEditService($em);
        $service->update($routine, new ArrayCollection());

        self::assertCount(0, $routine->routineExercises);
    }

    private function createRoutineExercise(string $exerciseName): RoutineExercise
    {
        $exercise = new Exercise();
        $exercise->name = $exerciseName;

        $routineExercise = new RoutineExercise();
        $routineExercise->exercise = $exercise;
        $routineExercise->position = 0;

        return $routineExercise;
    }
}
