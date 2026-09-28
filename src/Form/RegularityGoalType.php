<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\RegularityGoal;
use App\Enum\Entity\RegularityGoal\RegularityGoalDurationEnum;
use App\Enum\Entity\RegularityGoal\RegularityGoalPeriodEnum;
use App\Form\Trait\CalendarModalFieldsTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * « [3] séances par [semaine] pendant [4 semaines] à partir du [date] » — modale de l'onglet Calendrier.
 *
 * @extends AbstractType<RegularityGoal>
 */
final class RegularityGoalType extends AbstractType
{
    use CalendarModalFieldsTrait;

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sessionsPerPeriod', IntegerType::class, $this->sessionsOptions())
            ->add('period', EnumType::class, [
                ...$this->fieldOptions('regularity_goal.form.period_label'),
                'class' => RegularityGoalPeriodEnum::class,
                'choice_label' => static fn (RegularityGoalPeriodEnum $period): string => 'regularity_goal.period.' . $period->value,
                'choice_translation_domain' => 'navigation',
            ])
            ->add('duration', EnumType::class, [
                ...$this->fieldOptions('regularity_goal.form.duration_label'),
                'class' => RegularityGoalDurationEnum::class,
                'choice_label' => static fn (RegularityGoalDurationEnum $duration): TranslatableMessage => new TranslatableMessage('regularity_goal.duration', [
                    'weeks' => $duration->value,
                ], 'navigation'),
                'help' => 'regularity_goal.form.duration_help',
                'help_attr' => [
                    'class' => 'text-[11px] text-[#64748b] mt-1.5 leading-snug',
                ],
            ])
            // Jamais dans le passé : bloqué dans le calendrier, revérifié par la validation de l'entité.
            ->add('startDate', DateType::class, $this->dateOptions('regularity_goal.form.start_label', futureOnly: true));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegularityGoal::class,
            'csrf_protection' => true,
            'csrf_token_id' => 'regularity_goal_create',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionsOptions(): array
    {
        return [
            ...$this->fieldOptions('regularity_goal.form.sessions_label'),
            'attr' => [
                'class' => self::FIELD_CLASS,
                'min' => 1,
                'max' => RegularityGoalPeriodEnum::WEEK->maxSessions(),
                'inputmode' => 'numeric',
            ],
        ];
    }
}
