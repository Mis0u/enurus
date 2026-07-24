<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Formulaire admin uniquement (EasyAdmin) — domaine de traduction `admin`, français uniquement,
 * cf. décision "dashboard admin en français uniquement".
 *
 * @extends AbstractType<null>
 */
final class ContactRestrictionFormType extends AbstractType
{
    public const string CHOICE_ONE_WEEK = 'one_week';

    public const string CHOICE_ONE_MONTH = 'one_month';

    public const string CHOICE_PERMANENT = 'permanent';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('duration', ChoiceType::class, [
            'label' => $this->translator->trans('admin.contact_restriction.duration.label', [], 'admin', 'fr'),
            'choices' => [
                $this->translator->trans('admin.contact_restriction.duration.one_week', [], 'admin', 'fr') => self::CHOICE_ONE_WEEK,
                $this->translator->trans('admin.contact_restriction.duration.one_month', [], 'admin', 'fr') => self::CHOICE_ONE_MONTH,
                $this->translator->trans('admin.contact_restriction.duration.permanent', [], 'admin', 'fr') => self::CHOICE_PERMANENT,
            ],
            'expanded' => true,
            'constraints' => [
                new NotBlank(),
            ],
            'mapped' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'contact_thread_admin_block',
        ]);
    }
}
