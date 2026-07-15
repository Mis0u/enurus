<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Repository\ContactThreadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactThreadRepository::class)]
class ContactThread
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
    public User $owner {
        get {
            return $this->owner;
        }
        set(User $owner) {
            $this->owner = $owner;
        }
    }

    #[ORM\Column(type: 'string', enumType: ContactCategoryEnum::class)]
    public ContactCategoryEnum $category = ContactCategoryEnum::BUG {
        get {
            return $this->category;
        }
        set(?ContactCategoryEnum $category) {
            $this->category = $category ?? $this->category;
        }
    }

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 150)]
    public string $subject = '' {
        get {
            return $this->subject;
        }
        set(?string $subject) {
            $this->subject = ('' === $subject ? null : $subject) ?? $this->subject;
        }
    }

    #[ORM\Column(type: 'string', enumType: ContactThreadStatusEnum::class)]
    public ContactThreadStatusEnum $status = ContactThreadStatusEnum::AWAITING_ADMIN_REPLY {
        get {
            return $this->status;
        }
        set(?ContactThreadStatusEnum $status) {
            $this->status = $status ?? $this->status;
        }
    }

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt {
        get {
            return $this->createdAt;
        }
    }

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt {
        get {
            return $this->updatedAt;
        }
        set(\DateTimeImmutable $updatedAt) {
            $this->updatedAt = $updatedAt;
        }
    }

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $closedAt = null {
        get {
            return $this->closedAt;
        }
        set(?\DateTimeImmutable $closedAt) {
            $this->closedAt = $closedAt;
        }
    }

    /**
     * Masquage côté propriétaire uniquement — l'autre partie (future interface admin) garde sa
     * propre vue du fil indépendamment de ce champ.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $hiddenByUserAt = null {
        get {
            return $this->hiddenByUserAt;
        }
        set(?\DateTimeImmutable $hiddenByUserAt) {
            $this->hiddenByUserAt = $hiddenByUserAt;
        }
    }

    /**
     * @var Collection<int, ContactThreadMessage>
     */
    #[ORM\OneToMany(targetEntity: ContactThreadMessage::class, mappedBy: 'thread', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy([
        'createdAt' => 'ASC',
    ])]
    public Collection $messages {
        get {
            return $this->messages;
        }
    }

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->messages = new ArrayCollection();
    }

    public function addMessage(ContactThreadMessage $message): void
    {
        if (! $this->messages->contains($message)) {
            $this->messages->add($message);
            $message->thread = $this;
        }
    }
}
