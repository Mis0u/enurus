<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\DeloadPeriod;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<DeloadPeriod>
 */
final class DeloadPeriodType extends AbstractType
{
    private const string FIELD_CLASS = 'w-full bg-white/[0.04] border border-white/[0.07] rounded-xl px-3.5 py-3 text-[#f0f4ff] font-dm-sans text-sm outline-none focus:border-[#06b6d4] focus:bg-white/[0.06] transition-all';

    private const string LABEL_CLASS = 'block text-[10.5px] font-semibold text-slate-500 mb-1.5';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, $this->dateOptions('workout.list.calendar.modal.start_label'))
            ->add('endDate', DateType::class, $this->dateOptions('workout.list.calendar.modal.end_label'))
            ->add('note', TextareaType::class, [
                'label' => 'workout.list.calendar.modal.note_label',
                'translation_domain' => 'navigation',
                'required' => false,
                'attr' => [
                    'class' => self::FIELD_CLASS . ' resize-none',
                    'rows' => 2,
                    'placeholder' => $this->translator->trans('workout.list.calendar.modal.note_placeholder', [], 'navigation'),
                ],
                'label_attr' => [
                    'class' => self::LABEL_CLASS,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DeloadPeriod::class,
            'csrf_protection' => true,
            'csrf_token_id' => 'deload_period_create',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dateOptions(string $label): array
    {
        return [
            'label' => $label,
            'translation_domain' => 'navigation',
            'widget' => 'single_text',
            'html5' => false,
            'format' => 'yyyy-MM-dd',
            'input' => 'datetime_immutable',
            'attr' => [
                'class' => self::FIELD_CLASS,
                'data-controller' => 'workout--date-picker',
                'data-workout--date-picker-locale-value' => $this->currentLocale(),
            ],
            'label_attr' => [
                'class' => self::LABEL_CLASS,
            ],
        ];
    }

    private function currentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new \LogicException('Building the deload period form requires an active HTTP request.');
        }

        return $request->getLocale();
    }
}
