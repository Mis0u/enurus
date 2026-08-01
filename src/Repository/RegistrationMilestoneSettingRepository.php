<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RegistrationMilestoneSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RegistrationMilestoneSetting>
 */
class RegistrationMilestoneSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegistrationMilestoneSetting::class);
    }

    /**
     * Ligne unique, insérée par migration (jamais créée à la volée) — l'admin ne peut que la
     * modifier, jamais en créer une seconde (Action::NEW désactivée côté CRUD).
     */
    public function getSingleton(): RegistrationMilestoneSetting
    {
        $setting = $this->findOneBy([]);

        if (null === $setting) {
            throw new \LogicException('RegistrationMilestoneSetting row is missing — check that its seeding migration ran.');
        }

        return $setting;
    }
}
