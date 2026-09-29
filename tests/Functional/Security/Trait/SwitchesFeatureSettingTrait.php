<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Trait;

use App\Repository\FeatureSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Bascule un réglage de `FeatureSetting` pour la durée d'un test — annulé ensuite par le rollback
 * DAMA. À appeler après `createClient()`/`login()` (le conteneur doit déjà exister).
 */
trait SwitchesFeatureSettingTrait
{
    private function switchWorkoutPhotoUpload(bool $enabled): void
    {
        /** @var FeatureSettingRepository $repository */
        $repository = static::getContainer()->get(FeatureSettingRepository::class);
        $repository->getSingleton()->workoutPhotoUploadEnabled = $enabled;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();
    }
}
