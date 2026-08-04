<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractFormSecurityType<null>
 */
final class ChangePasswordFormType extends AbstractFormSecurityType
{
    private const array DATA_REPEAT_PASSWORD = [
        'data-password-validator-target' => 'inputRepeatPassword',
        'data-action' => 'input->password-validator#validate',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => $this->translator->trans('settings.password.current_password', [], 'navigation'),
                'label_attr' => [
                    'class' => self::LABEL_ATTR['class'],
                ],
                'constraints' => [
                    new NotBlank(),
                ],
                'attr' => array_merge(self::ATTR_FIELD_CLASS, [
                    'autocomplete' => 'current-password',
                ]),
                'mapped' => false,
            ])
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
        $updateUrl = $this->urlGenerator->generate('app_settings_password_update');

        $resolver->setDefaults([
            'attr' => [
                'class' => 'space-y-6',
                'data-controller' => 'password-validator settings--password-submit',
                'data-action' => 'submit->settings--password-submit#submit',
                'data-settings--password-submit-url-value' => $updateUrl,
                'data-settings--password-submit-success-message-value' => $this->translator->trans('settings.feedback.success', [], 'navigation'),
                'data-settings--password-submit-error-message-value' => $this->translator->trans('settings.feedback.error', [], 'navigation'),
            ],
            'csrf_token_id' => 'change_password',
            'action' => $updateUrl,
        ]);
    }
}
