<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DeletedAccountTraceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Trace laissée par un compte purgé (email, son hash SHA-256 et la date), retenue 6 mois puis
 * purgée (DeletedAccountTracePurgeCommand). Le hash sert à détecter une réinscription avec le même
 * email et à en notifier l'admin ; l'email, à savoir quel compte a été supprimé.
 */
#[ORM\Entity(repositoryClass: DeletedAccountTraceRepository::class)]
class DeletedAccountTrace
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

    #[ORM\Column(type: Types::STRING, length: 64)]
    public string $emailHash {
        get {
            return $this->emailHash;
        }
        set(string $emailHash) {
            $this->emailHash = $emailHash;
        }
    }

    /**
     * Null pour les traces enregistrées avant que l'email ne soit conservé (seul le hash l'était).
     */
    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    public ?string $email = null {
        get {
            return $this->email;
        }
        set(?string $email) {
            $this->email = $email;
        }
    }

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $deletedAt {
        get {
            return $this->deletedAt;
        }
        set(\DateTimeImmutable $deletedAt) {
            $this->deletedAt = $deletedAt;
        }
    }
}
