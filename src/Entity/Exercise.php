<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampTrait;
use App\Enum\Entity\Exercise\MeasurementType;
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
    #[Assert\NotBlank(message: 'exercise.name_not_blank')]
    #[Assert\Length(max: 150)]
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

    /**
     * Un exercice archivé n'apparaît plus dans la bibliothèque ni les sélecteurs (nouvelle routine/
     * séance), mais reste résolvable pour l'historique existant (WorkoutExercise/RoutineExercise) —
     * cf. ExerciseDeletionService.
     */
    #[ORM\Column]
    public bool $archived = false {
        get {
            return $this->archived;
        }
        set(bool $archived) {
            $this->archived = $archived;
        }
    }

    /**
     * Pilote quels champs de série sont exigés/affichés (poids+reps vs. durée) — voir
     * `ExerciseSet::duration`. Verrouillé côté formulaire dès que l'exercice a au moins une série
     * enregistrée (cf. ExerciseSetRepository::existsForExercise), pour ne pas laisser un historique
     * incohérent (des séries reps sans durée, ou l'inverse).
     */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: false, enumType: MeasurementType::class)]
    public MeasurementType $measurementType = MeasurementType::WEIGHT_REPS {
        get {
            return $this->measurementType;
        }
        set(?MeasurementType $measurementType) {
            $this->measurementType = $measurementType ?? $this->measurementType;
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
