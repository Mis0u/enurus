<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FeatureSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Ligne unique (créée par migration, jamais par fixtures — donnée de config, pas de démo) : les
 * interrupteurs des fonctionnalités que l'admin peut couper sans déploiement. Une fonctionnalité
 * coupée garde son code actif et testé ; seul son point d'entrée côté utilisateur disparaît.
 * `FeatureSettingRepository::getSingleton()` est le seul point d'accès. Nouvelle fonctionnalité
 * pilotable = nouvelle colonne ici, pas une nouvelle entité.
 */
#[ORM\Entity(repositoryClass: FeatureSettingRepository::class)]
class FeatureSetting
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?Uuid $id = null {
        get {
            return $this->id;
        }
    }

    /**
     * Ajout d'une photo à une séance (création et édition). Coupé : les photos existantes restent
     * affichées et supprimables — un utilisateur doit toujours pouvoir retirer ses données.
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $workoutPhotoUploadEnabled = false {
        get {
            return $this->workoutPhotoUploadEnabled;
        }
        set(bool $workoutPhotoUploadEnabled) {
            $this->workoutPhotoUploadEnabled = $workoutPhotoUploadEnabled;
        }
    }
}
