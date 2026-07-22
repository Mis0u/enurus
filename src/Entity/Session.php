<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Backing store for App\Security\Session\DoctrineSessionHandler. Rows are written/read via raw
 * DBAL from the handler (never through the EntityManager — session I/O happens on every request
 * and must not trigger a flush() of unrelated pending entities), this class only exists to give
 * the table a proper Doctrine mapping (migrations, schema, PHPStan).
 */
#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[ORM\Table(name: 'sessions')]
#[ORM\Index(name: 'idx_sessions_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_sessions_updated_at', columns: ['updated_at'])]
class Session
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

    #[ORM\Column(type: Types::STRING, length: 128, unique: true)]
    public string $sessionId {
        get {
            return $this->sessionId;
        }
        set(string $sessionId) {
            $this->sessionId = $sessionId;
        }
    }

    #[ORM\Column(type: Types::BLOB)]
    public mixed $data = null {
        get {
            return $this->data;
        }
        set(mixed $data) {
            $this->data = $data;
        }
    }

    #[ORM\Column(type: Types::INTEGER)]
    public int $lifetime {
        get {
            return $this->lifetime;
        }
        set(int $lifetime) {
            $this->lifetime = $lifetime;
        }
    }

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $updatedAt {
        get {
            return $this->updatedAt;
        }
        set(\DateTimeImmutable $updatedAt) {
            $this->updatedAt = $updatedAt;
        }
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: true, onDelete: 'CASCADE')]
    public ?User $user = null {
        get {
            return $this->user;
        }
        set(?User $user) {
            $this->user = $user;
        }
    }
}
