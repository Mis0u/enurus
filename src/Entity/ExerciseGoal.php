<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampTrait;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\ExerciseGoalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Un seul objectif actif (non atteint) par (owner, exercise) à la fois — mais pas de contrainte
 * unique en base : les objectifs atteints restent en historique indéfiniment plutôt que d'être
 * remplacés. Voir `App\Service\Goal\GoalStateResolver::findActiveGoal()` pour déterminer lequel,
 * parmi plusieurs lignes du même (owner, exercise), est l'objectif actif. L'état "atteint" n'est
 * jamais stocké ici, voir `App\Service\Goal\GoalProgressResolver`.
 *
 * `targetReps` est optionnel et ne concerne que `WEIGHT_REPS` : quand il est renseigné en plus de
 * `targetWeight`, l'objectif devient à deux dimensions ("6 reps à 100kg") — voir
 * `App\Service\Goal\GoalProgress` pour le calcul d'atteinte correspondant.
 */
#[ORM\Entity(repositoryClass: ExerciseGoalRepository::class)]
#[ORM\Table(name: 'exercise_goal')]
class ExerciseGoal
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

    /**
     * Copié depuis `Exercise::measurementType` à la création de l'objectif — fige le type de
     * mesure attendu par cet objectif même si l'exercice venait à changer.
     */
    #[ORM\Column(type: Types::STRING, length: 20, enumType: MeasurementType::class)]
    public MeasurementType $measurementType {
        get {
            return $this->measurementType;
        }
        set(MeasurementType $measurementType) {
            $this->measurementType = $measurementType;
        }
    }

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    public ?float $targetWeight = null {
        get {
            return $this->targetWeight;
        }
        set(?float $targetWeight) {
            $this->targetWeight = $targetWeight;
        }
    }

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    public ?int $targetReps = null {
        get {
            return $this->targetReps;
        }
        set(?int $targetReps) {
            $this->targetReps = $targetReps;
        }
    }

    #[ORM\Column(nullable: true)]
    public ?int $targetDuration = null {
        get {
            return $this->targetDuration;
        }
        set(?int $targetDuration) {
            $this->targetDuration = $targetDuration;
        }
    }

    #[ORM\Column(nullable: true)]
    public ?int $targetDistance = null {
        get {
            return $this->targetDistance;
        }
        set(?int $targetDistance) {
            $this->targetDistance = $targetDistance;
        }
    }

    /**
     * Un seul objectif préconfiguré prêt à être persisté — factorise la même construction entre
     * `ExerciseHistoryController` (affichage du formulaire) et `ExerciseGoalSaveController`
     * (traitement de la soumission).
     */
    public static function draftFor(User $owner, Exercise $exercise): self
    {
        $goal = new self();
        $goal->owner = $owner;
        $goal->exercise = $exercise;
        $goal->measurementType = $exercise->measurementType;

        return $goal;
    }

    /**
     * Le champ cible attendu dépend du type de mesure — même principe que
     * `ExerciseSet::validateByMeasurementType()`.
     */
    #[Assert\Callback]
    public function validateTargetByMeasurementType(ExecutionContextInterface $context): void
    {
        $target = match ($this->measurementType) {
            MeasurementType::WEIGHT_REPS => $this->targetWeight,
            MeasurementType::TIME => $this->targetDuration,
            MeasurementType::DISTANCE => $this->targetDistance,
        };

        if (null === $target || 0 >= $target) {
            $context->buildViolation('exercise_goal.target_required')
                ->atPath('target')
                ->setTranslationDomain('validators')
                ->addViolation();
        }
    }

    public function targetValue(): float
    {
        $target = match ($this->measurementType) {
            MeasurementType::WEIGHT_REPS => $this->targetWeight,
            MeasurementType::TIME => $this->targetDuration,
            MeasurementType::DISTANCE => $this->targetDistance,
        };

        if (null === $target) {
            throw new \LogicException('Goal target must be set before reading its value.');
        }

        return (float) $target;
    }
}
