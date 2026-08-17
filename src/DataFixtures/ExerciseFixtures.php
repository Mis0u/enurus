<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\DataFixtures\Service\File\FileService;
use App\DataFixtures\Service\Type\TypeService;
use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Entity\MuscleGroup;
use App\Entity\User;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Enum\Entity\Exercise\MeasurementType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ExerciseFixtures extends Fixture implements DependentFixtureInterface
{
    public const string REFERENCE_PREFIX = 'exercise_';

    public const string EXERCISE_REVERSE_FLY = 'Reverse fly';

    public const string EXERCISE_TIRAGE_SUPINATION = 'Tirage supination';

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
            $exercise->measurementType = $this->resolveMeasurementType($data);
            $exercise->bodyweightPercent = $this->resolveBodyweightPercent($data);

            $this->addMuscleToExercise($data, $exercise, MuscleTypeEnum::PRIMARY);
            $this->addMuscleToExercise($data, $exercise, MuscleTypeEnum::SECONDARY);

            $manager->persist($exercise);

            $this->addReference(\sprintf('%s%d', self::REFERENCE_PREFIX, $index), $exercise);
            ++$index;
        }
        $this->loadCustomExercises($manager);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            MuscleGroupFixtures::class,
            UserFixtures::class,
        ];
    }

    /**
     * Clé facultative dans Exercises.json — absente pour la quasi-totalité des exercices,
     * qui restent WEIGHT_REPS (valeur par défaut de l'entité).
     *
     * @param array<string, mixed> $data
     */
    private function resolveMeasurementType(array $data): MeasurementType
    {
        if (! isset($data['measurementType'])) {
            return MeasurementType::WEIGHT_REPS;
        }

        return MeasurementType::from($this->typeService->getString($data, 'measurementType'));
    }

    /**
     * Clé facultative dans Exercises.json — absente pour la quasi-totalité des exercices, qui
     * restent des exercices classiques (charge externe uniquement, pas de poids de corps).
     *
     * @param array<string, mixed> $data
     */
    private function resolveBodyweightPercent(array $data): ?float
    {
        if (! isset($data['bodyweightPercent'])) {
            return null;
        }

        return $this->typeService->getFloat($data, 'bodyweightPercent');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function addMuscleToExercise(array $data, Exercise $exercise, MuscleTypeEnum $muscleType): void
    {
        $muscleNames = $this->typeService->getStringArray($data, $muscleType->value);

        foreach ($muscleNames as $muscleName) {
            $this->attachMuscleGroup($exercise, $muscleName, $muscleType);
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

    private function loadCustomExercises(ObjectManager $manager): void
    {
        $this->loadReverseFlyExercise($manager);
        $this->loadTirageSupinationExercise($manager);
    }

    private function loadReverseFlyExercise(ObjectManager $manager): void
    {
        /** @var User $owner */
        $owner = $this->getReference(
            \sprintf('%s%s', UserFixtures::REFERENCE_PREFIX, UserFixtures::USER_REVERSE_FLY),
            User::class
        );

        $exercise = new Exercise();
        $exercise->name = self::EXERCISE_REVERSE_FLY;
        $exercise->isPublic = false;
        $exercise->owner = $owner;

        $this->attachMuscleGroup($exercise, 'name.posterior_deltoid', MuscleTypeEnum::PRIMARY);
        $this->attachMuscleGroup($exercise, 'name.traps_lower', MuscleTypeEnum::SECONDARY);

        $manager->persist($exercise);
    }

    private function loadTirageSupinationExercise(ObjectManager $manager): void
    {
        /** @var User $owner */
        $owner = $this->getReference(
            \sprintf('%s%s', UserFixtures::REFERENCE_PREFIX, UserFixtures::USER_TIRAGE_SUPINATION),
            User::class
        );

        $exercise = new Exercise();
        $exercise->name = self::EXERCISE_TIRAGE_SUPINATION;
        $exercise->isPublic = false;
        $exercise->owner = $owner;
        $exercise->description = 'Tirage à la poulie basse en supination, coudes le long du corps. Contractez les dorsaux en fin de mouvement.';

        $this->attachMuscleGroup($exercise, 'name.lats', MuscleTypeEnum::PRIMARY);
        $this->attachMuscleGroup($exercise, 'name.biceps', MuscleTypeEnum::SECONDARY);

        $manager->persist($exercise);
    }

    private function attachMuscleGroup(Exercise $exercise, string $muscleGroupName, MuscleTypeEnum $type): void
    {
        $exerciseMuscle = $this->createExerciseMuscle($exercise, $muscleGroupName, $type);

        if (null !== $exerciseMuscle) {
            $exercise->exerciseMuscles->add($exerciseMuscle);
        }
    }
}
