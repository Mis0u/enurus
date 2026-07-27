<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Entity\MuscleGroup;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Service\Entity\MuscleSorterService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MuscleSorterServiceTest extends TestCase
{
    public function testPrimaryMusclesAlwaysComeBeforeSecondaryOnes(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $exercise = $this->exerciseWith([
            [
                'name' => 'Zzz secondaire',
                'type' => MuscleTypeEnum::SECONDARY,
            ],
            [
                'name' => 'Aaa primaire',
                'type' => MuscleTypeEnum::PRIMARY,
            ],
        ]);

        $service = new MuscleSorterService($translator);
        $sorted = $service->sortByTypeThenName($exercise, 'fr');

        self::assertSame(MuscleTypeEnum::PRIMARY, $sorted[0]['type']);
        self::assertSame(MuscleTypeEnum::SECONDARY, $sorted[1]['type']);
    }

    public function testMusclesOfTheSameTypeAreSortedAlphabeticallyByTranslatedLabel(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap([
            ['muscle.quadriceps', [], 'muscle', 'fr', 'Zzz traduit'],
            ['muscle.biceps', [], 'muscle', 'fr', 'Aaa traduit'],
        ]);

        $exercise = $this->exerciseWith([
            [
                'name' => 'muscle.quadriceps',
                'type' => MuscleTypeEnum::PRIMARY,
            ],
            [
                'name' => 'muscle.biceps',
                'type' => MuscleTypeEnum::PRIMARY,
            ],
        ]);

        $service = new MuscleSorterService($translator);
        $sorted = $service->sortByTypeThenName($exercise, 'fr');

        self::assertSame('Aaa traduit', $sorted[0]['label']);
        self::assertSame('Zzz traduit', $sorted[1]['label']);
    }

    /**
     * @param list<array{name: string, type: MuscleTypeEnum}> $muscles
     */
    private function exerciseWith(array $muscles): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Test exercise';

        foreach ($muscles as $muscle) {
            $muscleGroup = new MuscleGroup();
            $muscleGroup->name = $muscle['name'];

            $exerciseMuscle = new ExerciseMuscle();
            $exerciseMuscle->muscleGroup = $muscleGroup;
            $exerciseMuscle->type = $muscle['type'];

            $exercise->exerciseMuscles->add($exerciseMuscle);
        }

        return $exercise;
    }
}
