<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\ExerciseMuscle;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Service\Entity\ExerciseMuscleValidationService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

final class ExerciseMuscleValidationServiceTest extends TestCase
{
    public function testHasPrimaryMuscleReturnsTrueWhenPrimaryPresent(): void
    {
        $primary = new ExerciseMuscle();
        $primary->type = MuscleTypeEnum::PRIMARY;

        $secondary = new ExerciseMuscle();
        $secondary->type = MuscleTypeEnum::SECONDARY;

        $service = new ExerciseMuscleValidationService();

        self::assertTrue($service->hasPrimaryMuscle(new ArrayCollection([$secondary, $primary])));
    }

    public function testHasPrimaryMuscleReturnsFalseWhenOnlySecondary(): void
    {
        $secondary = new ExerciseMuscle();
        $secondary->type = MuscleTypeEnum::SECONDARY;

        $service = new ExerciseMuscleValidationService();

        self::assertFalse($service->hasPrimaryMuscle(new ArrayCollection([$secondary])));
    }

    public function testHasPrimaryMuscleReturnsFalseForEmptyCollection(): void
    {
        $service = new ExerciseMuscleValidationService();

        self::assertFalse($service->hasPrimaryMuscle(new ArrayCollection()));
    }
}
