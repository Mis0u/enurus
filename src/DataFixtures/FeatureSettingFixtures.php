<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\FeatureSetting;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Repeuple la ligne unique de FeatureSetting après une purge dev/test (`doctrine:fixtures:load`
 * vide toutes les tables, y compris celles seedées par migration — même pattern que
 * ContactNotificationSettingFixtures). Valeurs par défaut de l'entité = celles de la migration
 * (upload de photo coupé). En prod, cette commande ne tourne jamais.
 */
class FeatureSettingFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $manager->persist(new FeatureSetting());
        $manager->flush();
    }
}
