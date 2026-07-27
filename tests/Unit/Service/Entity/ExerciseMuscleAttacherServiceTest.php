<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Entity\MuscleGroup;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Service\Entity\ExerciseMuscleAttacherService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ExerciseMuscleAttacherServiceTest extends TestCase
{
    public function testAttachLinksEachMuscleToTheExerciseAndPersistsIt(): void
    {
        $exercise = new Exercise();
        $exercise->name = 'Squat';

        $primary = $this->createExerciseMuscle(MuscleTypeEnum::PRIMARY);
        $secondary = $this->createExerciseMuscle(MuscleTypeEnum::SECONDARY);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('persist');

        $service = new ExerciseMuscleAttacherService($em);
        $service->attach($exercise, new ArrayCollection([$primary, $secondary]));

        self::assertCount(2, $exercise->exerciseMuscles);
        self::assertSame($exercise, $primary->exercise);
        self::assertSame($exercise, $secondary->exercise);
    }

    public function testAttachWithEmptyCollectionDoesNothing(): void
    {
        $exercise = new Exercise();
        $exercise->name = 'Squat';

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $service = new ExerciseMuscleAttacherService($em);
        $service->attach($exercise, new ArrayCollection());

        self::assertCount(0, $exercise->exerciseMuscles);
    }

    private function createExerciseMuscle(MuscleTypeEnum $type): ExerciseMuscle
    {
        $muscleGroup = new MuscleGroup();
        $muscleGroup->name = 'Quadriceps';

        $exerciseMuscle = new ExerciseMuscle();
        $exerciseMuscle->muscleGroup = $muscleGroup;
        $exerciseMuscle->type = $type;

        return $exerciseMuscle;
    }
}
