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
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
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

    private const string FIELD_CLASS = 'w-full bg-white/[0.04] border border-white/[0.07] rounded-xl px-3.5 py-3 text-[#f0f4ff] font-dm-sans text-sm outline-none focus:border-[#f43f5e] focus:bg-white/[0.06] transition-all';

    private const string LABEL_CLASS = 'block text-xs font-semibold text-slate-500 mb-2';

    private const string ROW_CLASS = 'flex flex-col';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool) $options['is_edit'];

        $builder
            ->add('performedAt', DateType::class, $this->performedAtOptions($isEdit))
            ->add('duration', IntegerType::class, $this->durationOptions())
            ->add('routine', EntityType::class, $this->routineOptions())
            ->add('workoutExercises', CollectionType::class, $this->workoutExercisesOptions())
            ->add('note', $isEdit ? TextareaType::class : HiddenType::class, $this->noteOptions($isEdit))
        ;

        $builder->addEventSubscriber(new WorkoutFormListener());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Workout::class,
            'is_edit' => false,
            'csrf_token_id' => 'workout',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function performedAtOptions(bool $isEdit): array
    {
        return [
            'widget' => 'single_text',
            'html5' => true,
            'input' => 'datetime_immutable',
            'label' => $this->translator->trans('workout.date', [], 'navigation'),
            'attr' => $isEdit ? $this->performedAtEditAttr() : $this->performedAtCreateAttr(),
            'label_attr' => [
                'class' => self::LABEL_CLASS,
            ],
            'row_attr' => [
                'class' => self::ROW_CLASS,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function performedAtEditAttr(): array
    {
        return [
            'class' => self::FIELD_CLASS,
            'max' => new \DateTimeImmutable('today')->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function performedAtCreateAttr(): array
    {
        return [
            'max' => new \DateTimeImmutable('today')->format('Y-m-d'),
            'data-controller' => 'date',
            'data-date-check-url-value' => $this->urlGenerator->generate('workout_check_date'),
            'data-date-message-value' => $this->translator->trans('workout.check_date.message', [
                'count' => '__COUNT__',
            ], 'navigation'),
            'data-date-confirm-button-value' => $this->translator->trans('workout.check_date.confirm', [], 'navigation'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function durationOptions(): array
    {
        return [
            'label' => $this->translator->trans('workout.duration_optional', [], 'navigation'),
            'required' => false,
            'attr' => [
                'placeholder' => $this->translator->trans('workout.duration_placeholder', [], 'navigation'),
                'min' => self::MIN_DURATION,
                'step' => self::STEP_DURATION,
                'class' => self::FIELD_CLASS . ' pr-12',
            ],
            'label_attr' => [
                'class' => self::LABEL_CLASS,
            ],
            'row_attr' => [
                'class' => self::ROW_CLASS,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function routineOptions(): array
    {
        return [
            'class' => Routine::class,
            'label' => $this->translator->trans('workout.routine.title', [], 'navigation'),
            'choice_label' => 'name',
            'required' => false,
            'placeholder' => $this->translator->trans('workout.routine.select', [], 'navigation'),
            'attr' => [
                'id' => 'workout-routine',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function workoutExercisesOptions(): array
    {
        return [
            'entry_type' => WorkoutExerciseType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'label' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noteOptions(bool $isEdit): array
    {
        return [
            'required' => false,
            'label' => false,
            'attr' => $isEdit ? $this->noteEditAttr() : $this->noteCreateAttr(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noteEditAttr(): array
    {
        return [
            'class' => 'w-full bg-white/[0.03] border border-white/[0.07] rounded-xl px-4 py-3.5 text-[#f0f4ff] font-dm-sans text-[13.5px] leading-relaxed min-h-[100px] resize-y outline-none focus:border-[#f43f5e] transition-all',
            'placeholder' => $this->translator->trans('workout.note.placeholder', [], 'navigation'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noteCreateAttr(): array
    {
        return [
            'id' => 'workout-note',
            'rows' => 4,
        ];
    }
}
