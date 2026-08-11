<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\ExerciseSetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
     * Toujours pertinent quel que soit le type de mesure de l'exercice : poids de travail pour
     * `WEIGHT_REPS`, charge additionnelle optionnelle pour `TIME` (ex. gainage lesté) — `0.0` y
     * signifie "au poids du corps". Le caractère obligatoire (>0) n'est imposé que pour
     * `WEIGHT_REPS`, voir `validateByMeasurementType()`.
     */
    #[ORM\Column(type: 'float')]
    public float $weight = 0.0 {
        get {
            return $this->weight;
        }
        set(?float $weight) {
            $this->weight = $weight ?? $this->weight;
        }
    }

    /**
     * Sans objet pour un exercice `TIME` (reste à sa valeur par défaut, ignoré des calculs de
     * tonnage/PR) — voir `validateByMeasurementType()`.
     */
    #[ORM\Column]
    public int $reps = 0 {
        get {
            return $this->reps;
        }
        set(?int $reps) {
            $this->reps = $reps ?? $this->reps;
        }
    }

    /**
     * Durée tenue, en secondes — sans objet pour un exercice `WEIGHT_REPS`. Pendant du PR "poids
     * max" pour les exercices `TIME` : c'est cette valeur, seule, qui détermine un record (le
     * poids éventuellement ajouté est ignoré du calcul, exactement comme les reps le sont
     * aujourd'hui pour le PR de poids).
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $duration = null {
        get {
            return $this->duration;
        }
        set(?int $duration) {
            $this->duration = $duration ?? $this->duration;
        }
    }

    /**
     * Les champs obligatoires dépendent du type de mesure de l'exercice parent — voir
     * `MeasurementType`. Centralisé ici plutôt qu'en attributs statiques `Assert\Positive`/
     * `NotBlank` sur `weight`/`reps`/`duration`, qui ne peuvent pas varier selon une autre entité.
     */
    #[Assert\Callback]
    public function validateByMeasurementType(ExecutionContextInterface $context): void
    {
        if (MeasurementType::TIME === $this->workoutExercise->exercise->measurementType) {
            if (0 >= $this->duration) {
                $context->buildViolation('exercise_set.duration_required')
                    ->atPath('duration')
                    ->setTranslationDomain('validators')
                    ->addViolation();
            }

            if (0 > $this->weight) {
                $context->buildViolation('exercise_set.weight_positive_or_zero')
                    ->atPath('weight')
                    ->setTranslationDomain('validators')
                    ->addViolation();
            }

            return;
        }

        if (0 >= $this->weight) {
            $context->buildViolation('exercise_set.weight_required')
                ->atPath('weight')
                ->setTranslationDomain('validators')
                ->addViolation();
        }

        if (0 >= $this->reps) {
            $context->buildViolation('exercise_set.reps_required')
                ->atPath('reps')
                ->setTranslationDomain('validators')
                ->addViolation();
        }
    }
}
