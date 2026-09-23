<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Repository\UserBadgeRepository;
use App\Service\Badge\BadgeKey;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Badge de palier obtenu par un utilisateur. Jamais créé ni supprimé ailleurs que dans
 * `BadgeSyncService` : un badge redevenu immérité (séance supprimée) est retiré, sauf
 * l'ancienneté (`BadgeFamilyEnum::isRevocable()`).
 *
 * `unlockedAt` est une date sans fuseau, comme `Workout::performedAt` (cf. CLAUDE.md).
 */
#[ORM\Entity(repositoryClass: UserBadgeRepository::class)]
#[ORM\Table(name: 'user_badge')]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_BADGE_OWNER_FAMILY_TIER', columns: ['owner_id', 'family', 'tier'])]
class UserBadge
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'badges')]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner {
        get {
            return $this->owner;
        }
        set(User $owner) {
            $this->owner = $owner;
        }
    }

    #[ORM\Column(type: Types::STRING, length: 20, enumType: BadgeFamilyEnum::class)]
    public BadgeFamilyEnum $family {
        get {
            return $this->family;
        }
        set(BadgeFamilyEnum $family) {
            $this->family = $family;
        }
    }

    #[ORM\Column(type: Types::SMALLINT, enumType: BadgeTierEnum::class)]
    public BadgeTierEnum $tier {
        get {
            return $this->tier;
        }
        set(BadgeTierEnum $tier) {
            $this->tier = $tier;
        }
    }

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $unlockedAt {
        get {
            return $this->unlockedAt;
        }
        set(\DateTimeImmutable $unlockedAt) {
            $this->unlockedAt = $unlockedAt;
        }
    }

    /**
     * Séance dont l'enregistrement a fait franchir le seuil — affichée sur sa page de détail.
     * Nulle pour l'ancienneté, pour un badge obtenu au chargement du dashboard, pour le
     * rattrapage d'un compte existant, ou si la séance a été supprimée depuis.
     */
    #[ORM\ManyToOne(targetEntity: Workout::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Workout $workout = null {
        get {
            return $this->workout;
        }
        set(?Workout $workout) {
            $this->workout = $workout;
        }
    }

    public static function unlock(User $owner, BadgeKey $key, \DateTimeImmutable $unlockedAt, ?Workout $workout): self
    {
        $badge = new self();
        $badge->owner = $owner;
        $badge->family = $key->family;
        $badge->tier = $key->tier;
        $badge->unlockedAt = $unlockedAt;
        $badge->workout = $workout;

        return $badge;
    }

    public function key(): BadgeKey
    {
        return new BadgeKey($this->family, $this->tier);
    }
}
