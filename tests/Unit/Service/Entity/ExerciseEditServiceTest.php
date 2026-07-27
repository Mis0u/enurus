<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Entity\MuscleGroup;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Service\Entity\ExerciseEditService;
use App\Service\Entity\ExerciseMuscleAttacherService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ExerciseEditServiceTest extends TestCase
{
    public function testEditReplacesOldMusclesWithNewOnes(): void
    {
        $exercise = new Exercise();
        $exercise->name = 'Squat';

        $oldMuscle = $this->createExerciseMuscle();
        $oldMuscle->exercise = $exercise;
        $exercise->exerciseMuscles->add($oldMuscle);

        $newMuscle = $this->createExerciseMuscle();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($oldMuscle);
        // 1 persist pour le nouveau muscle (via le vrai ExerciseMuscleAttacherService).
        $em->expects(self::once())->method('persist')->with($newMuscle);
        $em->expects(self::once())->method('flush');

        $service = new ExerciseEditService($em, new ExerciseMuscleAttacherService($em));
        $service->edit($exercise, new ArrayCollection([$newMuscle]));

        self::assertCount(1, $exercise->exerciseMuscles);
        self::assertSame($newMuscle, $exercise->exerciseMuscles->first());
    }

    public function testEditWithEmptyCollectionRemovesAllExistingMuscles(): void
    {
        $exercise = new Exercise();
        $exercise->name = 'Squat';

        $oldMuscle = $this->createExerciseMuscle();
        $oldMuscle->exercise = $exercise;
        $exercise->exerciseMuscles->add($oldMuscle);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($oldMuscle);
        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('flush');

        $service = new ExerciseEditService($em, new ExerciseMuscleAttacherService($em));
        $service->edit($exercise, new ArrayCollection());

        self::assertCount(0, $exercise->exerciseMuscles);
    }

    private function createExerciseMuscle(): ExerciseMuscle
    {
        $muscleGroup = new MuscleGroup();
        $muscleGroup->name = 'Quadriceps';

        $exerciseMuscle = new ExerciseMuscle();
        $exerciseMuscle->muscleGroup = $muscleGroup;
        $exerciseMuscle->type = MuscleTypeEnum::PRIMARY;

        return $exerciseMuscle;
    }
}
