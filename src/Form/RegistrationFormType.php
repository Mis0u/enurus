<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Enum\Entity\User\GenderEnum;
use App\Enum\TailwindClass\Form\Field\FieldClassEnum;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractFormSecurityType<User>
 */
final class RegistrationFormType extends AbstractFormSecurityType
{
    private const array DATA_ROUTE = [
        'data-route' => 'registration',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ClockInterface $clock
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('gender', EnumType::class, [
                'class' => GenderEnum::class,
                'choice_label' => fn (GenderEnum $gender): string => match ($gender) {
                    GenderEnum::MALE => $this->translator->trans('field.gender.male', [], 'common'),
                    GenderEnum::FEMALE => $this->translator->trans('field.gender.female', [], 'common'),
                },
                'label' => $this->translator->trans('field.gender.title', [], 'common'),
                'label_attr' => [
                    'class' => 'block text-sm font-semibold text-slate-200 mb-3',
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('nickname', TextType::class, [
                'label' => $this->translator->trans('registration.nickname.label', [], 'navigation'),
                'label_attr' => self::LABEL_ATTR,
                'attr' => [
                    'class' => FieldClassEnum::ATTRIBUTE_FIELD_CLASS->value,
                    'placeholder' => $this->translator->trans('registration.nickname.placeholder', [], 'navigation'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => $this->translator->trans('field.email', [], 'common'),
                'label_attr' => self::LABEL_ATTR,
                'attr' => [
                    'class' => FieldClassEnum::ATTRIBUTE_FIELD_CLASS->value,
                    'placeholder' => $this->translator->trans('field.email_placeholder', [], 'common'),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $this->translator->trans('field.password', [], 'common'),
                'label_attr' => self::LABEL_ATTR,
                'mapped' => false,
                'attr' => array_merge(self::ATTR_FIELD_CLASS, self::DATA_PASSWORD, self::DATA_ROUTE),
                'constraints' => [
                    new NotBlank(),
                    ...$this->passwordConstraints($this->translator),
                    new NotCompromisedPassword(),
                ],
            ])
            ->add('website', TextType::class, [
                'label' => $this->translator->trans('registration.website', [], 'navigation'),
                'attr' => [
                    'id' => $this->translator->trans('registration.website', [], 'navigation'),
                    'autocomplete' => 'off',
                ],
                'mapped' => false,
                'required' => false,
            ])
            ->add('formStarted', HiddenType::class, [
                'data' => $this->clock->now()->getTimestamp(),
                'mapped' => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'attr' => [
                'class' => 'space-y-6',
                'data-controller' => 'password-validator',
            ],
        ]);
    }
}
