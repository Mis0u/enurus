<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\Exercise;
use App\Service\Entity\ExerciseSorterService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ExerciseSorterServiceTest extends TestCase
{
    public function testCustomExercisesAreSortedByTheirRawNameNotTranslated(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $squat = $this->createExercise('Squat', isPublic: false);
        $bench = $this->createExercise('Bench press', isPublic: false);

        $service = new ExerciseSorterService($translator);
        $sorted = $service->sortByName([$squat, $bench], 'fr');

        self::assertSame(['Bench press', 'Squat'], array_map(static fn (Exercise $e): string => $e->name, $sorted));
    }

    public function testPublicExercisesAreSortedByTheirTranslatedName(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnMap([
            ['exercise.squat', [], 'exercise', null, 'Zzz traduit'],
            ['exercise.bench', [], 'exercise', null, 'Aaa traduit'],
        ]);

        $squat = $this->createExercise('exercise.squat', isPublic: true);
        $bench = $this->createExercise('exercise.bench', isPublic: true);

        $service = new ExerciseSorterService($translator);
        $sorted = $service->sortByName([$squat, $bench], 'fr');

        // Triés sur le libellé traduit ("Aaa" < "Zzz"), pas sur la clé de traduction brute.
        self::assertSame('exercise.bench', $sorted[0]->name);
        self::assertSame('exercise.squat', $sorted[1]->name);
    }

    private function createExercise(string $name, bool $isPublic): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = $name;
        $exercise->isPublic = $isPublic;

        return $exercise;
    }
}
