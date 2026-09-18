<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampTrait;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Connexion réciproque entre deux utilisateurs : une fois `ACCEPTED`, chacun voit le dashboard de
 * l'autre, quel que soit celui qui a envoyé la demande. La contrainte unique ne porte que sur le
 * sens (requester, addressee) — empêcher la demande inverse (B→A quand A→B existe) et l'auto-demande
 * (requester = addressee) relève du service de demande, une contrainte d'index sur la paire non
 * ordonnée n'étant pas exprimable via le mapping Doctrine.
 */
#[ORM\Entity]
#[ORM\Table(name: 'profile_connection')]
#[ORM\UniqueConstraint(name: 'UNIQ_PROFILE_CONNECTION_PAIR', fields: ['requester', 'addressee'])]
class ProfileConnection
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?Uuid $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $requester {
        get {
            return $this->requester;
        }
        set(User $requester) {
            $this->requester = $requester;
        }
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $addressee {
        get {
            return $this->addressee;
        }
        set(User $addressee) {
            $this->addressee = $addressee;
        }
    }

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ProfileConnectionStatusEnum::class)]
    public ProfileConnectionStatusEnum $status = ProfileConnectionStatusEnum::PENDING {
        get {
            return $this->status;
        }
        set(ProfileConnectionStatusEnum $status) {
            $this->status = $status;
        }
    }

    /**
     * Instant de la dernière réponse (acceptation, refus ou révocation) — nul tant que la demande
     * est en attente.
     */
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $respondedAt = null {
        get {
            return $this->respondedAt;
        }
        set(?\DateTimeImmutable $respondedAt) {
            $this->respondedAt = $respondedAt;
        }
    }
}
