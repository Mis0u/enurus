<?php

declare(strict_types=1);

namespace App\Tests\Functional\DataFixtures;

use App\Entity\Exercise;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\ExerciseRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Garde-fou de non-régression pour Exercises.json : Farmer walk et Plank doivent rester
 * DISTANCE/TIME (corrigés depuis WEIGHT_REPS par Version20260812143138, cf. CLAUDE.md TODO #27)
 * si quelqu'un retouche le JSON de référence sans y penser.
 */
final class ExerciseFixturesTest extends KernelTestCase
{
    public function testFarmerWalkIsADistanceBasedExercise(): void
    {
        self::bootKernel();
        $exercise = $this->findPublicExerciseByName('farmer_walk.name');

        $this->assertSame(MeasurementType::DISTANCE, $exercise->measurementType);
    }

    public function testPlankIsATimeBasedExercise(): void
    {
        self::bootKernel();
        $exercise = $this->findPublicExerciseByName('plank.name');

        $this->assertSame(MeasurementType::TIME, $exercise->measurementType);
    }

    private function findPublicExerciseByName(string $name): Exercise
    {
        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);
        $exercise = $exerciseRepository->findOneBy([
            'name' => $name,
            'owner' => null,
        ]);

        $this->assertInstanceOf(Exercise::class, $exercise, \sprintf('Exercice public "%s" introuvable.', $name));

        return $exercise;
    }
}
