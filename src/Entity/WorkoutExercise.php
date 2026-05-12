<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WorkoutExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WorkoutExerciseRepository::class)]
class WorkoutExercise
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

    #[ORM\ManyToOne(targetEntity: Workout::class, inversedBy: 'workoutExercises')]
    #[ORM\JoinColumn(nullable: false)]
    public Workout $workout {
        get {
            return $this->workout;
        }
        set(Workout $workout) {
            $this->workout = $workout;
        }
    }

    #[ORM\ManyToOne(targetEntity: Exercise::class)]
    #[ORM\JoinColumn(nullable: false)]
    public Exercise $exercise {
        get {
            return $this->exercise;
        }
        set(Exercise $exercise) {
            $this->exercise = $exercise;
        }
    }

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    public int $position {
        get {
            return $this->position;
        }
        set(int $position) {
            $this->position = $position;
        }
    }

    /**
     * @var Collection<int, ExerciseSet>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: ExerciseSet::class, mappedBy: 'workoutExercise', cascade: ['persist', 'remove'], orphanRemoval: true)]
    public Collection $exerciseSets {
        get {
            return $this->exerciseSets;
        }
    }

    public function __construct()
    {
        $this->exerciseSets = new ArrayCollection();
    }

    public function addExerciseSet(ExerciseSet $exerciseSet): void
    {
        if (! $this->exerciseSets->contains($exerciseSet)) {
            $this->exerciseSets->add($exerciseSet);
            $exerciseSet->workoutExercise = $this;
        }
    }

    public function removeExerciseSet(ExerciseSet $exerciseSet): void
    {
        $this->exerciseSets->removeElement($exerciseSet);
    }
}
