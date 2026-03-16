<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Routine;
use App\Entity\Workout;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @template TData of object|null
 * @extends AbstractType<TData>
 */
class WorkoutType extends AbstractType
{
    private const int MIN_DURATION = 0;

    private const int STEP_DURATION = 1;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = new \DateTimeImmutable('today');
        $builder
            ->add('performedAt', DateType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'label' => $this->translator->trans('workout.date', [], 'navigation'),
                'attr' => [
                    'max' => $today->format('Y-m-d'),
                ],
            ])
            ->add('duration', IntegerType::class, [
                'label' => $this->translator->trans('workout.duration_optional', [], 'navigation'),
                'attr' => [
                    'placeholder' => $this->translator->trans('workout.duration_placeholder', [], 'navigation'),
                    'min' => self::MIN_DURATION,
                    'step' => self::STEP_DURATION,
                ],
            ])
            ->add('routine', EntityType::class, [
                'class' => Routine::class,
                'label' => $this->translator->trans('workout.routine.title', [], 'navigation'),
                'choice_label' => 'name',
                'placeholder' => $this->translator->trans('workout.routine.select', [], 'navigation'),
                'attr' => [
                    'id' => 'workout-routine',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Workout::class,
        ]);
    }
}
