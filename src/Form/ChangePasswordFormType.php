<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\Password\PasswordRuleEnum;
use App\Enum\tailwind_class\form\field\FieldClassEnum;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractFormSecurityType<null>
 */
class ChangePasswordFormType extends AbstractFormSecurityType
{
    private const array DATA_REPEAT_PASSWORD = [
        'data-password-validator-target' => 'inputRepeatPassword',
        'data-action' => 'input->password-validator#validate',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator
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
                        'class' => FieldClassEnum::LABEL_ATTRIBUTE->value,
                    ],
                    'constraints' => [
                        new NotBlank(),
                        new Length(min: (int) PasswordRuleEnum::MIN_LENGTH->value, max: 4096),
                        new NotCompromisedPassword(),
                        new Regex(
                            pattern: PasswordRuleEnum::REGEX->value,
                            message: $this->translator->trans('sentence.password.security.regex', [], 'common')
                        ),
                    ],
                    'attr' => array_merge(self::ATTR_FIELD_CLASS, self::DATA_PASSWORD),
                ],
                'second_options' => [
                    'label' => $this->translator->trans('field.confirm_new_password', [], 'common'),
                    'label_attr' => [
                        'class' => FieldClassEnum::LABEL_ATTRIBUTE->value,
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
        ]);
    }
}
