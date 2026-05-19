<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\DataFixtures\Service\File\FileService;
use App\DataFixtures\Service\Type\TypeService;
use App\Entity\MuscleGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class MuscleGroupFixtures extends Fixture
{
    public const string REFERENCE_PREFIX = 'muscle_group_';

    public function __construct(
        private readonly FileService $fileService,
        private readonly TypeService $typeService
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $muscleGroupArray = $this->fileService->loadJsonFile('Muscle-groups.json');

        foreach ($muscleGroupArray as $data) {
            $muscleGroup = new MuscleGroup();
            $muscleGroup->name = $this->typeService->getString($data, 'name');
            $muscleGroup->position = $this->typeService->getInt($data, 'position');
            $muscleGroup->svgIds = $this->typeService->getStringArray($data, 'svgIds');
            $manager->persist($muscleGroup);
            $this->addReference(\sprintf('%s%s', self::REFERENCE_PREFIX, $muscleGroup->name), $muscleGroup);
        }

        $manager->flush();
    }
}
