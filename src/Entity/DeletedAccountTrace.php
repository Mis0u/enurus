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
 * Trace minimale (hash SHA-256 de l'email + date) laissée par un compte purgé — sert à détecter
 * une réinscription avec le même email et à en notifier l'admin (TODO #21). Jamais l'email en
 * clair : seul un hash est conservé, retenu 30 jours puis purgé (DeletedAccountTracePurgeCommand).
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
