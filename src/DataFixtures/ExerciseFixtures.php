<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\DataFixtures\Service\File\FileService;
use App\DataFixtures\Service\Type\TypeService;
use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Entity\MuscleGroup;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ExerciseFixtures extends Fixture implements DependentFixtureInterface
{
    public const string REFERENCE_PREFIX = 'exercise_';

    public function __construct(
        private readonly FileService $fileService,
        private readonly TypeService $typeService
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $exercicesArray = $this->fileService->loadJsonFile('Exercises.json');
        $index = 0;

        foreach ($exercicesArray as $data) {
            $exercise = new Exercise();
            $exercise->name = $this->typeService->getString($data, 'name');
            $exercise->description = $this->typeService->getString($data, 'description');
            $exercise->isPublic = $this->typeService->getBool($data, 'isPublic');

            $this->addMuscleToExercise($data, $exercise, MuscleTypeEnum::PRIMARY);
            $this->addMuscleToExercise($data, $exercise, MuscleTypeEnum::SECONDARY);

            $manager->persist($exercise);

            $this->addReference(\sprintf('%s%d', self::REFERENCE_PREFIX, $index), $exercise);
            ++$index;
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            MuscleGroupFixtures::class,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function addMuscleToExercise(array $data, Exercise $exercise, MuscleTypeEnum $muscleType): void
    {
        $muscleNames = $this->typeService->getStringArray($data, $muscleType->value);

        foreach ($muscleNames as $muscleName) {
            $exerciseMuscle = $this->createExerciseMuscle($exercise, $muscleName, $muscleType);
            if (null !== $exerciseMuscle) {
                $exercise->exerciseMuscles->add($exerciseMuscle);
            }
        }
    }

    private function createExerciseMuscle(Exercise $exercise, string $muscleName, MuscleTypeEnum $type): ?ExerciseMuscle
    {
        $refKey = \sprintf('%s%s', MuscleGroupFixtures::REFERENCE_PREFIX, $muscleName);

        if (! $this->hasReference($refKey, MuscleGroup::class)) {
            return null;
        }

        $muscleGroup = $this->getReference($refKey, MuscleGroup::class);

        $exerciseMuscle = new ExerciseMuscle();
        $exerciseMuscle->exercise = $exercise;
        $exerciseMuscle->muscleGroup = $muscleGroup;
        $exerciseMuscle->type = $type;

        return $exerciseMuscle;
    }
}
