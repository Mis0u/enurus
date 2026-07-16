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

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotBlank]
    #[Assert\LessThanOrEqual('today', message: 'workout.performed_at.today')]
    public \DateTimeImmutable $performedAt {
        get {
            return $this->performedAt;
        }
        set(?\DateTimeImmutable $performedAt) {
            if (null === $performedAt) {
                return;
            }
            $this->performedAt = $performedAt;
        }
    }

    #[ORM\Column(nullable: true)]
    #[Assert\Positive()]
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
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: WorkoutExercise::class, mappedBy: 'workout', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $workoutExercises {
        get {
            return $this->workoutExercises;
        }
    }

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $note = null {
        get {
            return $this->note;
        }
        set(?string $note) {
            $this->note = $note;
        }
    }

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $photoPath = null {
        get {
            return $this->photoPath;
        }
        set(?string $photoPath) {
            $this->photoPath = $photoPath;
        }
    }

    public function __construct()
    {
        $this->workoutExercises = new ArrayCollection();
        $this->performedAt = new \DateTimeImmutable();
    }

    public function addWorkoutExercise(WorkoutExercise $workoutExercise): void
    {
        if (! $this->workoutExercises->contains($workoutExercise)) {
            $this->workoutExercises->add($workoutExercise);
            $workoutExercise->workout = $this;
        }
    }

    public function removeWorkoutExercise(WorkoutExercise $workoutExercise): void
    {
        $this->workoutExercises->removeElement($workoutExercise);
    }
}
