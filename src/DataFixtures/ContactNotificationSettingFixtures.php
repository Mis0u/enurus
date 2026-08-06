<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ContactNotificationSetting;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Repeuple la ligne unique de ContactNotificationSetting après une purge dev/test
 * (`doctrine:fixtures:load` vide toutes les tables, y compris celles seedées par migration —
 * même pattern que RegistrationMilestoneSettingFixtures). En prod, cette commande ne tourne
 * jamais : la ligne insérée par la migration reste la seule source de vérité.
 */
class ContactNotificationSettingFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $setting = new ContactNotificationSetting();
        $manager->persist($setting);
        $manager->flush();
    }
}
