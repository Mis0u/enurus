<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Exercise;
use App\Form\Type\ExerciseMusclesType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<Exercise>
 */
final class ExerciseType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('exercise.form.name_placeholder', [], 'navigation'),
                    'autocomplete' => 'off',
                    'maxlength' => 100,
                    'class' => 'w-full bg-[#0a0f1a] border border-white/[0.07] rounded-lg px-4 py-3 text-[#f1f5f9] font-dm-sans text-sm outline-none transition-all duration-200 focus:border-[#f43f5e] focus:shadow-[0_0_0_3px_rgba(244,63,94,0.12)] placeholder:text-[#334155]',
                ],
            ])
            ->add('muscles', ExerciseMusclesType::class, [
                'label' => false,
                'mapped' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => $this->translator->trans('exercise.form.description_placeholder', [], 'navigation'),
                    'rows' => 4,
                    'class' => 'mt-4 w-full bg-[#0a0f1a] border border-white/[0.07] rounded-lg px-4 py-3 text-[#f1f5f9] font-dm-sans text-[13.5px] leading-relaxed min-h-[96px] resize-y outline-none transition-all duration-200 focus:border-[#f43f5e] focus:shadow-[0_0_0_3px_rgba(244,63,94,0.12)] placeholder:text-[#334155]',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Exercise::class,
            'translation_domain' => 'messages',
        ]);
    }
}
