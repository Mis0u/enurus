<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form\DataTransformer;

use App\Entity\ExerciseMuscle;
use App\Entity\MuscleGroup;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Form\DataTransformer\ExerciseMuscleDataTransformer;
use App\Repository\MuscleGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Uid\Uuid;

final class ExerciseMuscleDataTransformerTest extends TestCase
{
    public function testTransformWithNullReturnsEmptyString(): void
    {
        $transformer = new ExerciseMuscleDataTransformer($this->createStub(MuscleGroupRepository::class));

        self::assertSame('', $transformer->transform(null));
    }

    public function testTransformWithEmptyCollectionReturnsEmptyString(): void
    {
        $transformer = new ExerciseMuscleDataTransformer($this->createStub(MuscleGroupRepository::class));

        self::assertSame('', $transformer->transform(new ArrayCollection()));
    }

    public function testTransformEncodesMuscleIdAndTypeAsJson(): void
    {
        $transformer = new ExerciseMuscleDataTransformer($this->createStub(MuscleGroupRepository::class));

        $muscleGroup = new MuscleGroup();
        $muscleGroup->id = Uuid::fromString('11111111-1111-1111-1111-111111111111');

        $exerciseMuscle = new ExerciseMuscle();
        $exerciseMuscle->muscleGroup = $muscleGroup;
        $exerciseMuscle->type = MuscleTypeEnum::PRIMARY;

        $json = $transformer->transform(new ArrayCollection([$exerciseMuscle]));

        self::assertSame(
            '[{"id":"11111111-1111-1111-1111-111111111111","type":"primary"}]',
            $json,
        );
    }

    public function testTransformIsIdempotentWhenCalledTwiceOnTheSameCollection(): void
    {
        // Régression pour le piège documenté (double appel transform() à l'édition) :
        // transformer une même Collection deux fois de suite doit produire le même JSON.
        $transformer = new ExerciseMuscleDataTransformer($this->createStub(MuscleGroupRepository::class));

        $muscleGroup = new MuscleGroup();
        $muscleGroup->id = Uuid::fromString('11111111-1111-1111-1111-111111111111');

        $exerciseMuscle = new ExerciseMuscle();
        $exerciseMuscle->muscleGroup = $muscleGroup;
        $exerciseMuscle->type = MuscleTypeEnum::SECONDARY;

        $collection = new ArrayCollection([$exerciseMuscle]);

        self::assertSame($transformer->transform($collection), $transformer->transform($collection));
    }

    public function testReverseTransformWithEmptyStringReturnsEmptyCollection(): void
    {
        $transformer = new ExerciseMuscleDataTransformer($this->createStub(MuscleGroupRepository::class));

        self::assertCount(0, $transformer->reverseTransform(''));
    }

    public function testReverseTransformBuildsExerciseMusclesFromJson(): void
    {
        $muscleGroup = new MuscleGroup();
        $muscleGroup->id = Uuid::fromString('11111111-1111-1111-1111-111111111111');

        $muscleGroupRepository = $this->createStub(MuscleGroupRepository::class);
        $muscleGroupRepository->method('find')->willReturn($muscleGroup);

        $transformer = new ExerciseMuscleDataTransformer($muscleGroupRepository);

        $collection = $transformer->reverseTransform('[{"id":"11111111-1111-1111-1111-111111111111","type":"secondary"}]');

        self::assertCount(1, $collection);
        $exerciseMuscle = $collection->first();
        self::assertInstanceOf(ExerciseMuscle::class, $exerciseMuscle);
        self::assertSame(MuscleTypeEnum::SECONDARY, $exerciseMuscle->type);
        self::assertSame($muscleGroup, $exerciseMuscle->muscleGroup);
    }

    public function testReverseTransformWithInvalidJsonThrows(): void
    {
        $transformer = new ExerciseMuscleDataTransformer($this->createStub(MuscleGroupRepository::class));

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('not-json');
    }

    public function testReverseTransformWithUnknownMuscleGroupThrows(): void
    {
        $muscleGroupRepository = $this->createStub(MuscleGroupRepository::class);
        $muscleGroupRepository->method('find')->willReturn(null);

        $transformer = new ExerciseMuscleDataTransformer($muscleGroupRepository);

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('[{"id":"unknown","type":"primary"}]');
    }

    public function testReverseTransformWithInvalidMuscleTypeThrows(): void
    {
        $muscleGroup = new MuscleGroup();
        $muscleGroup->id = Uuid::fromString('11111111-1111-1111-1111-111111111111');

        $muscleGroupRepository = $this->createStub(MuscleGroupRepository::class);
        $muscleGroupRepository->method('find')->willReturn($muscleGroup);

        $transformer = new ExerciseMuscleDataTransformer($muscleGroupRepository);

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('[{"id":"11111111-1111-1111-1111-111111111111","type":"unknown"}]');
    }
}
