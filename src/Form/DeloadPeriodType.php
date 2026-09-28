<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\DeloadPeriod;
use App\Form\Trait\CalendarModalFieldsTrait;
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
    use CalendarModalFieldsTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Un repos se programme : aujourd'hui ou plus tard (revérifié par la validation de l'entité).
            ->add('startDate', DateType::class, $this->dateOptions('workout.list.calendar.modal.start_label', futureOnly: true))
            ->add('endDate', DateType::class, $this->dateOptions('workout.list.calendar.modal.end_label', futureOnly: true))
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
}
