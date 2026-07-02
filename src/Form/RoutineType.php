<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Routine;
use App\Form\Type\RoutineExercisesType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<Routine>
 */
final class RoutineType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, $this->nameOptions())
            ->add('description', TextareaType::class, $this->descriptionOptions())
            ->add('exercises', RoutineExercisesType::class, $this->exercisesOptions());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Routine::class,
            'csrf_protection' => true,
            'csrf_token_id' => 'routine',
            'attr' => [
                'novalidate' => 'novalidate',
                'class' => 'flex flex-col gap-6',
                'data-action' => 'submit->routine--create#submitForm submit->routine--edit#submitForm',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function nameOptions(): array
    {
        return [
            'label' => 'routine.form.name',
            'translation_domain' => 'navigation',
            'attr' => [
                'autocomplete' => 'off',
                'maxlength' => 100,
                'class' => 'w-full bg-[#080e1a] border border-white/[0.07] rounded-[10px] px-4 py-3 text-[#f0f4ff] font-dm-sans text-sm outline-none transition-all duration-200 placeholder:text-[#4a5568] focus:border-rose-500/50 focus:shadow-[0_0_0_3px_rgba(244,63,94,0.08)]',
                'placeholder' => $this->translator->trans('routine.form.name_placeholder', [], 'navigation'),
                'data-routine--create-target' => 'nameInput',
                'data-routine--edit-target' => 'nameInput',
                'data-action' => 'input->routine--create#updateHeader input->routine--edit#updateHeader',
            ],
            'label_attr' => [
                'class' => 'block font-rajdhani text-[12px] font-semibold uppercase tracking-[0.6px] text-[#4a5568] mb-2.5',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function descriptionOptions(): array
    {
        return [
            'label' => false,
            'required' => false,
            'attr' => [
                'rows' => 3,
                'placeholder' => $this->translator->trans('routine.form.description_placeholder', [], 'navigation'),
                'class' => 'w-full bg-[#080e1a] border border-white/[0.07] rounded-[10px] px-4 py-3 text-[#f0f4ff] font-dm-sans text-sm outline-none resize-none transition-all duration-200 placeholder:text-[#4a5568] focus:border-rose-500/50 focus:shadow-[0_0_0_3px_rgba(244,63,94,0.08)]',
                'data-routine--create-target' => 'accordionBody',
                'data-routine--edit-target' => 'accordionBody',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exercisesOptions(): array
    {
        return [
            'label' => false,
            'mapped' => false,
            'attr' => [
                'data-routine--create-target' => 'exercisesInput',
                'data-routine--edit-target' => 'exercisesInput',
            ],
        ];
    }
}
