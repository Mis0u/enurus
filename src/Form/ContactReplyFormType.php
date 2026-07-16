<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\TailwindClass\Form\Field\FieldClassEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<null>
 */
final class ContactReplyFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('message', TextareaType::class, [
                'label' => $this->translator->trans('contact.field.message', [], 'navigation'),
                'label_attr' => [
                    'class' => FieldClassEnum::LABEL_ATTRIBUTE->value,
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 10, max: 5000),
                ],
                'attr' => [
                    'class' => FieldClassEnum::ATTRIBUTE_FIELD_CLASS->value,
                    'rows' => 5,
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
                'enctype' => 'multipart/form-data',
            ],
            'csrf_token_id' => 'contact_reply',
        ]);
    }
}
