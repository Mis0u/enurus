<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContactThreadMessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactThreadMessageRepository::class)]
class ContactThreadMessage
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

    #[ORM\ManyToOne(targetEntity: ContactThread::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ContactThread $thread {
        get {
            return $this->thread;
        }
        set(ContactThread $thread) {
            $this->thread = $thread;
        }
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $author {
        get {
            return $this->author;
        }
        set(User $author) {
            $this->author = $author;
        }
    }

    /**
     * Snapshot au moment de l'envoi — jamais dérivé des rôles actuels de `author`, pour que
     * l'historique reste correct même si les rôles changent plus tard.
     */
    #[ORM\Column]
    public bool $fromAdmin = false {
        get {
            return $this->fromAdmin;
        }
        set(bool $fromAdmin) {
            $this->fromAdmin = $fromAdmin;
        }
    }

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 5000)]
    public string $body = '' {
        get {
            return $this->body;
        }
        set(?string $body) {
            $this->body = ('' === $body ? null : $body) ?? $this->body;
        }
    }

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $imagePath = null {
        get {
            return $this->imagePath;
        }
        set(?string $imagePath) {
            $this->imagePath = $imagePath;
        }
    }

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt {
        get {
            return $this->createdAt;
        }
    }

    /**
     * Uniquement pertinent quand `fromAdmin` est vrai : `null` = non lu par le propriétaire du fil.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $readAt = null {
        get {
            return $this->readAt;
        }
        set(?\DateTimeImmutable $readAt) {
            $this->readAt = $readAt;
        }
    }

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
