<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ExerciseSetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ExerciseSetRepository::class)]
class ExerciseSet
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

    #[ORM\ManyToOne(targetEntity: WorkoutExercise::class, inversedBy: 'exerciseSets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public WorkoutExercise $workoutExercise {
        get {
            return $this->workoutExercise;
        }
        set(WorkoutExercise $workoutExercise) {
            $this->workoutExercise = $workoutExercise;
        }
    }

    #[ORM\Column]
    public int $position {
        get {
            return $this->position;
        }
        set(int $position) {
            $this->position = $position;
        }
    }

    #[ORM\Column(type: 'float')]
    public float $weight {
        get {
            return $this->weight;
        }
        set(float $weight) {
            $this->weight = $weight;
        }
    }

    #[ORM\Column]
    public int $reps {
        get {
            return $this->reps;
        }
        set(int $reps) {
            $this->reps = $reps;
        }
    }
}
