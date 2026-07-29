<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContactPollOptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactPollOptionRepository::class)]
class ContactPollOption
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

    #[ORM\ManyToOne(targetEntity: ContactBroadcast::class, inversedBy: 'pollOptions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public ContactBroadcast $broadcast {
        get {
            return $this->broadcast;
        }
        set(ContactBroadcast $broadcast) {
            $this->broadcast = $broadcast;
        }
    }

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    public string $label = '' {
        get {
            return $this->label;
        }
        set(string $label) {
            $this->label = $label;
        }
    }

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    public int $position = 0 {
        get {
            return $this->position;
        }
        set(int $position) {
            $this->position = $position;
        }
    }

    /**
     * Libellé traduit par langue (code `LocaleAllowedEnum`), rempli par
     * `SendContactBroadcastMessageHandler` en même temps que le sujet/corps de la diffusion.
     * Absent tant que la traduction n'a pas encore eu lieu pour cette langue.
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: 'json', nullable: false, options: [
        'default' => '[]',
    ])]
    public array $translatedLabels = [] {
        get {
            return $this->translatedLabels;
        }
        set(array $translatedLabels) {
            $this->translatedLabels = $translatedLabels;
        }
    }

    public function labelFor(string $locale): string
    {
        return $this->translatedLabels[$locale] ?? $this->label;
    }
}
