<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RegistrationMilestoneSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ligne unique (créée par migration, jamais par fixtures — donnée de config, pas de démo) : le pas
 * X entre deux notifications de palier d'inscriptions (500ème, 1000ème user...), piloté en admin.
 * `RegistrationMilestoneSettingRepository::getSingleton()` est le seul point d'accès.
 */
#[ORM\Entity(repositoryClass: RegistrationMilestoneSettingRepository::class)]
class RegistrationMilestoneSetting
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

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive]
    public int $step = 500 {
        get {
            return $this->step;
        }
        set(int $step) {
            $this->step = $step;
        }
    }
}
