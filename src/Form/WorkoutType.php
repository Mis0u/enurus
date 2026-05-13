<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Routine;
use App\Entity\Workout;
use App\EventListener\Form\WorkoutFormListener;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
        private readonly UrlGeneratorInterface $urlGenerator,
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
                    'data-controller' => 'date',
                    'data-date-check-url-value' => $this->urlGenerator->generate('workout_check_date'),
                    'data-date-message-value' => $this->translator->trans('workout.check_date.message', [
                        'count' => '__COUNT__',
                    ], 'navigation'),
                    'data-date-confirm-button-value' => $this->translator->trans('workout.check_date.confirm', [], 'navigation'),
                ],
            ])
            ->add('duration', IntegerType::class, [
                'label' => $this->translator->trans('workout.duration_optional', [], 'navigation'),
                'required' => false,
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
                'required' => false,
                'placeholder' => $this->translator->trans('workout.routine.select', [], 'navigation'),
                'attr' => [
                    'id' => 'workout-routine',
                ],
            ])
            ->add('workoutExercises', CollectionType::class, [
                'entry_type' => WorkoutExerciseType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
            ])
            ->add('note', HiddenType::class, [
                'required' => false,
                'label' => false,
                'attr' => [
                    'id' => 'workout-note',
                    'rows' => 4,
                ],
            ])
        ;

        $builder->addEventSubscriber(new WorkoutFormListener());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Workout::class,
        ]);
    }
}
