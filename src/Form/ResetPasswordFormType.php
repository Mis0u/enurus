<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractFormSecurityType<null>
 */
final class ResetPasswordFormType extends AbstractFormSecurityType
{
    private const array DATA_REPEAT_PASSWORD = [
        'data-password-validator-target' => 'inputRepeatPassword',
        'data-action' => 'input->password-validator#validate',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => $this->translator->trans('field.new_password', [], 'common'),
                    'label_attr' => [
                        'class' => self::LABEL_ATTR['class'],
                    ],
                    'constraints' => [
                        new NotBlank(),
                        ...$this->passwordConstraints($this->translator),
                    ],
                    'attr' => array_merge(self::ATTR_FIELD_CLASS, self::DATA_PASSWORD),
                ],
                'second_options' => [
                    'label' => $this->translator->trans('field.confirm_new_password', [], 'common'),
                    'label_attr' => [
                        'class' => self::LABEL_ATTR['class'],
                    ],
                    'attr' => array_merge(self::ATTR_FIELD_CLASS, self::DATA_REPEAT_PASSWORD),
                ],
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'class' => 'space-y-6',
                'data-controller' => 'password-validator',
            ],
            'csrf_token_id' => 'reset_password',
        ]);
    }
}
