<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FeatureSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FeatureSetting>
 */
class FeatureSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeatureSetting::class);
    }

    /**
     * Ligne unique, insérée par migration (jamais créée à la volée) — l'admin ne peut que la
     * modifier, jamais en créer une seconde (Action::NEW désactivée côté CRUD).
     */
    public function getSingleton(): FeatureSetting
    {
        $setting = $this->findOneBy([]);

        if (null === $setting) {
            throw new \LogicException('FeatureSetting row is missing — check that its seeding migration ran.');
        }

        return $setting;
    }

    public function isWorkoutPhotoUploadEnabled(): bool
    {
        return $this->getSingleton()->workoutPhotoUploadEnabled;
    }
}
