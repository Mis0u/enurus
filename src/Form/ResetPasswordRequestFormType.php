<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ResetPasswordRequest;
use App\Enum\tailwind_class\form\field\FieldClassEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<ResetPasswordRequest>
 */
class ResetPasswordRequestFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'attr' => [
                    'class' => FieldClassEnum::ATTRIBUTE_FIELD_CLASS->value,
                    'placeholder' => $this->translator->trans('field.email_placeholder', [], 'common'),
                ],
                'label_attr' => [
                    'class' => FieldClassEnum::LABEL_ATTRIBUTE->value,
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => [
                'class' => 'space-y-6',
            ],
        ]);
    }
}
