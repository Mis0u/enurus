<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Contact\ContactBroadcastTargetEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Repository\ContactBroadcastRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Snapshot d'un envoi groupé admin (`ContactThreadComposeService::composeToAudience()`) — un seul
 * enregistrement par envoi, quel que soit le nombre de destinataires. Chaque destinataire garde
 * son propre `ContactThread` (nécessaire pour son fil personnel et le Voter), relié à ce snapshot
 * via `ContactThread::$broadcast` pour un affichage groupé côté admin ("Diffusions").
 */
#[ORM\Entity(repositoryClass: ContactBroadcastRepository::class)]
class ContactBroadcast
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

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $sentBy {
        get {
            return $this->sentBy;
        }
        set(User $sentBy) {
            $this->sentBy = $sentBy;
        }
    }

    #[ORM\Column(length: 150)]
    public string $subject = '' {
        get {
            return $this->subject;
        }
        set(string $subject) {
            $this->subject = $subject;
        }
    }

    #[ORM\Column(type: 'text')]
    public string $body = '' {
        get {
            return $this->body;
        }
        set(string $body) {
            $this->body = $body;
        }
    }

    #[ORM\Column(type: 'string', enumType: ContactBroadcastTargetEnum::class)]
    public ContactBroadcastTargetEnum $target {
        get {
            return $this->target;
        }
        set(ContactBroadcastTargetEnum $target) {
            $this->target = $target;
        }
    }

    #[ORM\Column(type: 'string', enumType: LocaleAllowedEnum::class, nullable: true)]
    public ?LocaleAllowedEnum $locale = null {
        get {
            return $this->locale;
        }
        set(?LocaleAllowedEnum $locale) {
            $this->locale = $locale;
        }
    }

    #[ORM\Column]
    public int $recipientCount = 0 {
        get {
            return $this->recipientCount;
        }
        set(int $recipientCount) {
            $this->recipientCount = $recipientCount;
        }
    }

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $sentAt {
        get {
            return $this->sentAt;
        }
    }

    /**
     * `cascade: remove` + `orphanRemoval` : supprimer une diffusion supprime aussi les fils
     * individuels qu'elle a créés chez chaque destinataire — ils n'existent que comme artefact de
     * cet envoi et ne sont jamais répondables (cf. ContactThreadVoter, catégorie INFORMATIVE).
     *
     * @var Collection<int, ContactThread>
     */
    #[ORM\OneToMany(targetEntity: ContactThread::class, mappedBy: 'broadcast', cascade: ['remove'], orphanRemoval: true)]
    public Collection $threads {
        get {
            return $this->threads;
        }
    }

    public function __construct()
    {
        $this->sentAt = new \DateTimeImmutable();
        $this->threads = new ArrayCollection();
    }
}
