<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContactPollVoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Un seul vote par destinataire, jamais modifiable — porté par une relation `OneToOne` sur
 * `ContactThread` (chaque destinataire d'une diffusion a déjà exactement un fil pour celle-ci,
 * garanti par le fan-out de `SendContactBroadcastMessageHandler`) plutôt que par un couple
 * `(broadcast, user)` dupliqué : la contrainte d'unicité de la relation OneToOne empêche un
 * second vote au niveau base, pas seulement par une vérification applicative.
 */
#[ORM\Entity(repositoryClass: ContactPollVoteRepository::class)]
class ContactPollVote
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

    #[ORM\OneToOne(targetEntity: ContactThread::class, inversedBy: 'pollVote')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    public ContactThread $thread {
        get {
            return $this->thread;
        }
        set(ContactThread $thread) {
            $this->thread = $thread;
        }
    }

    #[ORM\ManyToOne(targetEntity: ContactPollOption::class)]
    #[ORM\JoinColumn(nullable: false)]
    public ContactPollOption $option {
        get {
            return $this->option;
        }
        set(ContactPollOption $option) {
            $this->option = $option;
        }
    }

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $votedAt {
        get {
            return $this->votedAt;
        }
    }

    public function __construct()
    {
        $this->votedAt = new \DateTimeImmutable();
    }
}
