<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\tailwind_class\form\field\FieldClassEnum;
use Symfony\Component\Form\AbstractType;
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
 * @extends AbstractType<array{plainPassword: string}>
 */
class ChangePasswordFormType extends AbstractType
{
    public const MIN_LENGTH = 12;

    private const ATTR_FIELD_CLASS = [
        'class' => FieldClassEnum::ATTRIBUTE_FIELD_CLASS->value,
        'placeholder' => '••••••••',
    ];

    private const DATA_PASSWORD = [
        'data-password-validator-target' => 'inputPassword',
        'data-action' => 'input->password-validator#validate',
        'data-min-length' => self::MIN_LENGTH,
    ];

    private const DATA_REPEAT_PASSWORD = [
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
                        new Length(min: self::MIN_LENGTH, max: 4096),
                        new NotCompromisedPassword(),
                        new Regex(
                            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!?%$*&]).{12,}$/',
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
