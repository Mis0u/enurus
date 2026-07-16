<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\Exercise;
use App\Entity\RoutineExercise;
use App\Entity\User;
use App\Service\Entity\RoutineExerciseAccessService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

final class RoutineExerciseAccessServiceTest extends TestCase
{
    public function testAllAccessibleReturnsTrueForPublicExercise(): void
    {
        $user = new User();
        $exercise = new Exercise();
        $exercise->isPublic = true;

        $routineExercise = new RoutineExercise();
        $routineExercise->exercise = $exercise;

        $service = new RoutineExerciseAccessService();

        self::assertTrue($service->allAccessible(new ArrayCollection([$routineExercise]), $user));
    }

    public function testAllAccessibleReturnsTrueForOwnedPrivateExercise(): void
    {
        $user = new User();
        $exercise = new Exercise();
        $exercise->isPublic = false;
        $exercise->owner = $user;

        $routineExercise = new RoutineExercise();
        $routineExercise->exercise = $exercise;

        $service = new RoutineExerciseAccessService();

        self::assertTrue($service->allAccessible(new ArrayCollection([$routineExercise]), $user));
    }

    public function testAllAccessibleReturnsFalseForPrivateExerciseOfAnotherUser(): void
    {
        $user = new User();
        $otherUser = new User();
        $exercise = new Exercise();
        $exercise->isPublic = false;
        $exercise->owner = $otherUser;

        $routineExercise = new RoutineExercise();
        $routineExercise->exercise = $exercise;

        $service = new RoutineExerciseAccessService();

        self::assertFalse($service->allAccessible(new ArrayCollection([$routineExercise]), $user));
    }

    public function testAllAccessibleReturnsTrueForEmptyCollection(): void
    {
        $user = new User();
        $service = new RoutineExerciseAccessService();

        self::assertTrue($service->allAccessible(new ArrayCollection(), $user));
    }
}
