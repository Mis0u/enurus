<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\RegistrationMilestoneSetting;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Repeuple la ligne unique de RegistrationMilestoneSetting après une purge dev/test
 * (`doctrine:fixtures:load` vide toutes les tables, y compris celles seedées par migration —
 * même pattern que MuscleGroupFixtures). En prod, cette commande ne tourne jamais : la ligne
 * insérée par la migration reste la seule source de vérité.
 */
class RegistrationMilestoneSettingFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $setting = new RegistrationMilestoneSetting();
        $manager->persist($setting);
        $manager->flush();
    }
}
