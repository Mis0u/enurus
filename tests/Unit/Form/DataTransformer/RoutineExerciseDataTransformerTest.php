<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form\DataTransformer;

use App\Entity\Exercise;
use App\Entity\RoutineExercise;
use App\Form\DataTransformer\RoutineExerciseDataTransformer;
use App\Repository\ExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Uid\Uuid;

final class RoutineExerciseDataTransformerTest extends TestCase
{
    public function testTransformWithNullReturnsEmptyString(): void
    {
        $transformer = new RoutineExerciseDataTransformer($this->createStub(ExerciseRepository::class));

        self::assertSame('', $transformer->transform(null));
    }

    public function testTransformWithEmptyCollectionReturnsEmptyString(): void
    {
        $transformer = new RoutineExerciseDataTransformer($this->createStub(ExerciseRepository::class));

        self::assertSame('', $transformer->transform(new ArrayCollection()));
    }

    public function testTransformPassesThroughAnAlreadySerializedString(): void
    {
        // Le hidden input peut soumettre directement une chaîne JSON déjà transformée par le JS
        // côté front — le transformer ne doit pas la re-sérialiser (cf. piège double transform()).
        $transformer = new RoutineExerciseDataTransformer($this->createStub(ExerciseRepository::class));

        self::assertSame('[{"id":"x","position":0}]', $transformer->transform('[{"id":"x","position":0}]'));
    }

    public function testTransformEncodesExerciseIdAndPositionAsJson(): void
    {
        $transformer = new RoutineExerciseDataTransformer($this->createStub(ExerciseRepository::class));

        $exercise = new Exercise();
        $exercise->name = 'Squat';
        $exercise->id = Uuid::fromString('11111111-1111-1111-1111-111111111111');

        $routineExercise = new RoutineExercise();
        $routineExercise->exercise = $exercise;
        $routineExercise->position = 2;

        $json = $transformer->transform(new ArrayCollection([$routineExercise]));

        self::assertSame(
            '[{"id":"11111111-1111-1111-1111-111111111111","position":2}]',
            $json,
        );
    }

    public function testReverseTransformWithEmptyStringReturnsEmptyCollection(): void
    {
        $transformer = new RoutineExerciseDataTransformer($this->createStub(ExerciseRepository::class));

        self::assertCount(0, $transformer->reverseTransform(''));
    }

    public function testReverseTransformBuildsRoutineExercisesFromJson(): void
    {
        $exercise = new Exercise();
        $exercise->name = 'Squat';
        $exercise->id = Uuid::fromString('11111111-1111-1111-1111-111111111111');

        $exerciseRepository = $this->createStub(ExerciseRepository::class);
        $exerciseRepository->method('find')->willReturn($exercise);

        $transformer = new RoutineExerciseDataTransformer($exerciseRepository);

        $collection = $transformer->reverseTransform('[{"id":"11111111-1111-1111-1111-111111111111","position":3}]');

        self::assertCount(1, $collection);
        $routineExercise = $collection->first();
        self::assertInstanceOf(RoutineExercise::class, $routineExercise);
        self::assertSame(3, $routineExercise->position);
        self::assertSame($exercise, $routineExercise->exercise);
    }

    public function testReverseTransformWithInvalidJsonThrows(): void
    {
        $transformer = new RoutineExerciseDataTransformer($this->createStub(ExerciseRepository::class));

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('not-json');
    }

    public function testReverseTransformWithUnknownExerciseThrows(): void
    {
        $exerciseRepository = $this->createStub(ExerciseRepository::class);
        $exerciseRepository->method('find')->willReturn(null);

        $transformer = new RoutineExerciseDataTransformer($exerciseRepository);

        $this->expectException(TransformationFailedException::class);

        $transformer->reverseTransform('[{"id":"unknown","position":0}]');
    }
}
