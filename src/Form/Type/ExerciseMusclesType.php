<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Form\DataTransformer\ExerciseMuscleDataTransformer;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Hidden field carrying JSON-encoded muscle assignments.
 * The DataTransformer handles the JSON ↔ Collection conversion.
 *
 * @extends AbstractType<Collection<int, \App\Entity\ExerciseMuscle>>
 */
final class ExerciseMusclesType extends AbstractType
{
    public function __construct(
        private readonly ExerciseMuscleDataTransformer $transformer,
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
