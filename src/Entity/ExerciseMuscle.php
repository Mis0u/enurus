<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Repository\ExerciseMuscleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ExerciseMuscleRepository::class)]
class ExerciseMuscle
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

    #[ORM\ManyToOne(targetEntity: Exercise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Exercise $exercise {
        get {
            return $this->exercise;
        }
        set(Exercise $exercise) {
            $this->exercise = $exercise;
        }
    }

    #[ORM\ManyToOne(targetEntity: MuscleGroup::class)]
    #[ORM\JoinColumn(nullable: false)]
    public MuscleGroup $muscleGroup {
        get {
            return $this->muscleGroup;
        }
        set(MuscleGroup $muscleGroup) {
            $this->muscleGroup = $muscleGroup;
        }
    }

    #[ORM\Column(length: 20, enumType: MuscleTypeEnum::class)]
    public MuscleTypeEnum $type {
        get {
            return $this->type;
        }
        set(MuscleTypeEnum $type) {
            $this->type = $type;
        }
    }
}
