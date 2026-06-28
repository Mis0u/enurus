<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Form\DataTransformer\RoutineExerciseDataTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Champ hidden qui transporte le JSON des exercices ordonnés.
 * Format : [{"id": "uuid", "position": 1}, ...]
 *
 * Réutilisé identiquement en création et en édition.
 *
 * @extends AbstractType<string>
 */
final class RoutineExercisesType extends AbstractType
{
    public function __construct(
        private readonly RoutineExerciseDataTransformer $transformer,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer($this->transformer);
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }
}
