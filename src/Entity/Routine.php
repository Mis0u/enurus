<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampTrait;
use App\Repository\RoutineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RoutineRepository::class)]
class Routine
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'routines')]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner {
        get {
            return $this->owner;
        }
        set(User $owner) {
            $this->owner = $owner;
        }
    }

    #[ORM\Column(length: 100)]
    public string $name {
        get {
            return $this->name;
        }
        set(string $name) {
            $this->name = $name;
        }
    }

    /**
     * @var Collection<int, RoutineExercise>
     */
    #[ORM\OneToMany(targetEntity: RoutineExercise::class, mappedBy: 'routine', cascade: ['persist', 'remove'])]
    public Collection $routineExercises {
        get {
            return $this->routineExercises;
        }
    }

    public function __construct()
    {
        $this->routineExercises = new ArrayCollection();
    }
}
