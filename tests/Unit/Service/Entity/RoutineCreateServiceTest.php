<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\Exercise;
use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Entity\User;
use App\Service\Entity\RoutineCreateService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RoutineCreateServiceTest extends TestCase
{
    public function testCreateAssignsOwnerAttachesExercisesAndPersists(): void
    {
        $routine = new Routine();
        $routine->name = 'Push day';
        $owner = new User();
        $owner->email = 'owner@test.com';

        $routineExercise = $this->createRoutineExercise();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($routine);
        $em->expects(self::once())->method('flush');

        $service = new RoutineCreateService($em);
        $service->create($routine, $owner, new ArrayCollection([$routineExercise]));

        self::assertSame($owner, $routine->owner);
        self::assertCount(1, $routine->routineExercises);
        self::assertSame($routine, $routineExercise->routine);
    }

    public function testCreateWithNoExercisesStillPersistsTheRoutine(): void
    {
        $routine = new Routine();
        $routine->name = 'Push day';
        $owner = new User();
        $owner->email = 'owner@test.com';

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($routine);
        $em->expects(self::once())->method('flush');

        $service = new RoutineCreateService($em);
        $service->create($routine, $owner, new ArrayCollection());

        self::assertCount(0, $routine->routineExercises);
    }

    private function createRoutineExercise(): RoutineExercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Squat';

        $routineExercise = new RoutineExercise();
        $routineExercise->exercise = $exercise;
        $routineExercise->position = 0;

        return $routineExercise;
    }
}
