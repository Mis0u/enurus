<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Enum\Entity\Exercise\MeasurementType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<ExerciseGoal>
 */
final class ExerciseGoalType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Exercise $exercise */
        $exercise = $options['exercise'];

        match ($exercise->measurementType) {
            MeasurementType::WEIGHT_REPS => $builder
                ->add('targetWeight', NumberType::class, null !== $exercise->bodyweightPercent
                    ? $this->fieldOptions('exercise.goal.form.target_added_weight_bodyweight_label', 'exercise.goal.form.target_added_weight_bodyweight_placeholder')
                    : $this->fieldOptions('exercise.goal.form.target_weight_label', 'exercise.goal.form.target_weight_placeholder'))
                ->add('targetReps', IntegerType::class, $this->fieldOptions('exercise.goal.form.target_reps_label', 'exercise.goal.form.target_reps_placeholder', required: false)),
            MeasurementType::TIME => $builder
                ->add('targetDuration', IntegerType::class, $this->fieldOptions('exercise.goal.form.target_duration_label', 'exercise.goal.form.target_duration_placeholder'))
                ->add('targetWeight', NumberType::class, $this->fieldOptions('exercise.goal.form.target_added_weight_label', 'exercise.goal.form.target_added_weight_placeholder', required: false)),
            MeasurementType::DISTANCE => $builder
                ->add('targetDistance', IntegerType::class, $this->fieldOptions('exercise.goal.form.target_distance_label', 'exercise.goal.form.target_distance_placeholder'))
                ->add('targetWeight', NumberType::class, $this->fieldOptions('exercise.goal.form.target_added_weight_label', 'exercise.goal.form.target_added_weight_placeholder', required: false)),
        };
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExerciseGoal::class,
            'csrf_protection' => true,
            'csrf_token_id' => 'exercise_goal',
        ]);

        $resolver->setRequired('exercise');
        $resolver->setAllowedTypes('exercise', Exercise::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldOptions(string $label, string $placeholder, bool $required = true): array
    {
        return [
            'label' => $label,
            'translation_domain' => 'navigation',
            'required' => $required,
            'attr' => [
                'min' => $required ? 0 : 1,
                'placeholder' => $this->translator->trans($placeholder, [], 'navigation'),
                'class' => 'w-full bg-[#080e1a] border border-white/[0.07] rounded-[10px] px-4 py-3 text-[#f0f4ff] font-dm-sans text-sm outline-none transition-all duration-200 focus:border-rose-500/50 focus:shadow-[0_0_0_3px_rgba(244,63,94,0.08)] placeholder:text-[#4a5568]',
            ],
        ];
    }
}
