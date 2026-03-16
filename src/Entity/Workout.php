<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WorkoutRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WorkoutRepository::class)]
class Workout
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

    #[ORM\ManyToOne(targetEntity: Routine::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?Routine $routine = null {
        get {
            return $this->routine;
        }
        set(?Routine $routine) {
            $this->routine = $routine;
        }
    }

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotBlank]
    #[Assert\LessThanOrEqual('today', message: 'workout.performed_at.today')]
    public \DateTimeInterface $performedAt {
        get {
            return $this->performedAt;
        }
        set(\DateTimeInterface $performedAt) {
            $this->performedAt = $performedAt;
        }
    }

    #[ORM\Column(nullable: true)]
    #[Assert\Positive()]
    #[Assert\LessThanOrEqual('1', message: 'workout.performed_at.today')]
    public ?int $duration = null {
        get {
            return $this->duration;
        }
        set(?int $duration) {
            $this->duration = $duration;
        }
    }

    /**
     * @var Collection<int, WorkoutExercise>
     */
    #[ORM\OneToMany(targetEntity: WorkoutExercise::class, mappedBy: 'session', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $workoutExercises {
        get {
            return $this->workoutExercises;
        }
    }

    public function __construct()
    {
        $this->workoutExercises = new ArrayCollection();
    }
}
