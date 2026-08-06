<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContactNotificationSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Ligne unique (créée par migration, jamais par fixtures — donnée de config, pas de démo) :
 * coupe-circuit global des notifications Telegram envoyées à l'admin sur nouveau message
 * `ContactThread` (cf. ContactThreadAdminNotifierService), pour absorber un afflux massif de
 * messages sans devoir désinstaller le bot. `ContactNotificationSettingRepository::getSingleton()`
 * est le seul point d'accès. Ne coupe jamais les emails, seulement Telegram.
 */
#[ORM\Entity(repositoryClass: ContactNotificationSettingRepository::class)]
class ContactNotificationSetting
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

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $telegramNotificationsEnabled = true {
        get {
            return $this->telegramNotificationsEnabled;
        }
        set(bool $telegramNotificationsEnabled) {
            $this->telegramNotificationsEnabled = $telegramNotificationsEnabled;
        }
    }
}
