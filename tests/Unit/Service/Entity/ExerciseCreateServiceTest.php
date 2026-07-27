<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Entity\MuscleGroup;
use App\Entity\User;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Service\Entity\ExerciseCreateService;
use App\Service\Entity\ExerciseMuscleAttacherService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ExerciseCreateServiceTest extends TestCase
{
    public function testCreateAssignsOwnerAttachesMusclesAndPersists(): void
    {
        $exercise = new Exercise();
        $exercise->name = 'Squat';
        $owner = new User();
        $owner->email = 'owner@test.com';

        $muscle = $this->createExerciseMuscle();

        $em = $this->createMock(EntityManagerInterface::class);
        // 1 persist pour le muscle (via le vrai ExerciseMuscleAttacherService) + 1 pour l'exercice.
        $em->expects(self::exactly(2))->method('persist');
        $em->expects(self::once())->method('flush');

        $service = new ExerciseCreateService($em, new ExerciseMuscleAttacherService($em));
        $service->create($exercise, $owner, new ArrayCollection([$muscle]));

        self::assertSame($owner, $exercise->owner);
        self::assertCount(1, $exercise->exerciseMuscles);
    }

    public function testCreateWithNoMusclesStillPersistsTheExercise(): void
    {
        $exercise = new Exercise();
        $exercise->name = 'Squat';
        $owner = new User();
        $owner->email = 'owner@test.com';

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($exercise);
        $em->expects(self::once())->method('flush');

        $service = new ExerciseCreateService($em, new ExerciseMuscleAttacherService($em));
        $service->create($exercise, $owner, new ArrayCollection());

        self::assertSame($owner, $exercise->owner);
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
