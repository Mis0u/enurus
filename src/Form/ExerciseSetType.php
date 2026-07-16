<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ExerciseSet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ExerciseSet>
 */
final class ExerciseSetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('weight', NumberType::class)
            ->add('reps', IntegerType::class)
            ->add('position', IntegerType::class, [
                'label' => false,
                'attr' => [
                    'hidden' => true,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExerciseSet::class,
        ]);
    }
}
