<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampTrait;
use App\Repository\ExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExerciseRepository::class)]
class Exercise
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

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    public string $name {
        get {
            return $this->name;
        }
        set(?string $name) {
            $this->name = trim($name ?? '');
        }
    }

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $description = null {
        get {
            return $this->description;
        }
        set(?string $description) {
            $this->description = $description;
        }
    }

    #[ORM\Column]
    public bool $isPublic = false {
        get {
            return $this->isPublic;
        }
        set(bool $isPublic) {
            $this->isPublic = $isPublic;
        }
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?User $owner = null {
        get {
            return $this->owner;
        }
        set(?User $owner) {
            $this->owner = $owner;
        }
    }

    /**
     * @var Collection<int, ExerciseMuscle>
     */
    #[ORM\OneToMany(targetEntity: ExerciseMuscle::class, mappedBy: 'exercise', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $exerciseMuscles {
        get {
            return $this->exerciseMuscles;
        }
    }

    public function __construct()
    {
        $this->exerciseMuscles = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
