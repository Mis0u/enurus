<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait TimestampTrait
{
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    public \DateTimeImmutable $createdAt {
        get {
            return $this->createdAt;
        }
        set(\DateTimeImmutable $createdAt) {
            $this->createdAt = $createdAt;
        }
    }

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    public ?\DateTimeImmutable $updatedAt = null {
        get {
            return $this->updatedAt;
        }
        set(?\DateTimeImmutable $updatedAt) {
            $this->updatedAt = $updatedAt;
        }
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Gedmo\Blameable(on: 'create')]
    public ?User $createdBy = null {
        get {
            return $this->createdBy;
        }
        set(?User $createdBy) {
            $this->createdBy = $createdBy;
        }
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Gedmo\Blameable(on: 'update')]
    public ?User $updatedBy = null {
        get {
            return $this->updatedBy;
        }
        set(?User $updatedBy) {
            $this->updatedBy = $updatedBy;
        }
    }
}
