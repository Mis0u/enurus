<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire admin uniquement (EasyAdmin), français en dur — pas de domaine de traduction du
 * site, cf. décision "dashboard admin en français uniquement".
 *
 * @extends AbstractType<null>
 */
final class ContactRestrictionFormType extends AbstractType
{
    public const string CHOICE_ONE_WEEK = 'one_week';

    public const string CHOICE_ONE_MONTH = 'one_month';

    public const string CHOICE_PERMANENT = 'permanent';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('duration', ChoiceType::class, [
            'label' => 'Durée du blocage',
            'choices' => [
                '1 semaine' => self::CHOICE_ONE_WEEK,
                '1 mois' => self::CHOICE_ONE_MONTH,
                'Permanent' => self::CHOICE_PERMANENT,
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
