<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Entity\MuscleGroup;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Service\Entity\ExercisePrimaryMuscleIdsResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ExercisePrimaryMuscleIdsResolverTest extends TestCase
{
    public function testResolveJoinsSvgIdsOfPrimaryMusclesOnly(): void
    {
        $exercise = $this->exerciseWith([
            [
                'svgIds' => ['chest-1', 'chest-2'],
                'type' => MuscleTypeEnum::PRIMARY,
            ],
            [
                'svgIds' => ['triceps-1'],
                'type' => MuscleTypeEnum::SECONDARY,
            ],
        ]);

        $resolver = new ExercisePrimaryMuscleIdsResolver();
        $result = $resolver->resolve([$exercise]);

        self::assertSame('chest-1,chest-2', $result[(string) $exercise->id]);
    }

    public function testResolveSecondaryJoinsSvgIdsOfSecondaryMusclesOnly(): void
    {
        $exercise = $this->exerciseWith([
            [
                'svgIds' => ['chest-1'],
                'type' => MuscleTypeEnum::PRIMARY,
            ],
            [
                'svgIds' => ['triceps-1', 'triceps-2'],
                'type' => MuscleTypeEnum::SECONDARY,
            ],
        ]);

        $resolver = new ExercisePrimaryMuscleIdsResolver();
        $result = $resolver->resolveSecondary([$exercise]);

        self::assertSame('triceps-1,triceps-2', $result[(string) $exercise->id]);
    }

    public function testResolveWithNoPrimaryMuscleReturnsAnEmptyString(): void
    {
        $exercise = $this->exerciseWith([
            [
                'svgIds' => ['triceps-1'],
                'type' => MuscleTypeEnum::SECONDARY,
            ],
        ]);

        $resolver = new ExercisePrimaryMuscleIdsResolver();
        $result = $resolver->resolve([$exercise]);

        self::assertSame('', $result[(string) $exercise->id]);
    }

    public function testResolvePrimaryMuscleGroupIdsReturnsUniqueMuscleGroupIds(): void
    {
        $muscleGroupId = Uuid::v4();
        $exercise = new Exercise();
        $exercise->name = 'Bench press';
        $exercise->id = Uuid::v4();

        $muscleGroup = new MuscleGroup();
        $muscleGroup->id = $muscleGroupId;
        $muscleGroup->name = 'Chest';

        // Deux ExerciseMuscle PRIMARY pointant vers le même MuscleGroup : l'id ne doit apparaître
        // qu'une seule fois dans le résultat (déduplication via clé de tableau).
        foreach ([1, 2] as $_) {
            $exerciseMuscle = new ExerciseMuscle();
            $exerciseMuscle->muscleGroup = $muscleGroup;
            $exerciseMuscle->type = MuscleTypeEnum::PRIMARY;
            $exercise->exerciseMuscles->add($exerciseMuscle);
        }

        $resolver = new ExercisePrimaryMuscleIdsResolver();
        $result = $resolver->resolvePrimaryMuscleGroupIds([$exercise]);

        self::assertSame((string) $muscleGroupId, $result[(string) $exercise->id]);
    }

    /**
     * @param list<array{svgIds: list<string>, type: MuscleTypeEnum}> $muscles
     */
    private function exerciseWith(array $muscles): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Bench press';
        $exercise->id = Uuid::v4();

        foreach ($muscles as $muscle) {
            $muscleGroup = new MuscleGroup();
            $muscleGroup->id = Uuid::v4();
            $muscleGroup->name = 'Muscle';
            $muscleGroup->svgIds = $muscle['svgIds'];

            $exerciseMuscle = new ExerciseMuscle();
            $exerciseMuscle->muscleGroup = $muscleGroup;
            $exerciseMuscle->type = $muscle['type'];

            $exercise->exerciseMuscles->add($exerciseMuscle);
        }

        return $exercise;
    }
}
