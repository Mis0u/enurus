<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RoutineExerciseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RoutineExerciseRepository::class)]
class RoutineExercise
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

    #[ORM\ManyToOne(targetEntity: Routine::class, inversedBy: 'routineExercises')]
    #[ORM\JoinColumn(nullable: false)]
    public Routine $routine {
        get {
            return $this->routine;
        }
        set(Routine $routine) {
            $this->routine = $routine;
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
    public int $position {
        get {
            return $this->position;
        }
        set(int $position) {
            $this->position = $position;
        }
    }
}
