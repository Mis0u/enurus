<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\TailwindClass\Form\Field\FieldClassEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractType<null>
 */
final class ContactFormType extends AbstractType
{
    private const array LABEL_ATTR = [
        'class' => FieldClassEnum::LABEL_ATTRIBUTE->value,
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', EnumType::class, [
                'class' => ContactCategoryEnum::class,
                'choices' => [
                    ContactCategoryEnum::BUG,
                    ContactCategoryEnum::SUGGESTION,
                    ContactCategoryEnum::QUESTION,
                    ContactCategoryEnum::LOVE,
                    ContactCategoryEnum::OTHER,
                ],
                'choice_label' => fn (ContactCategoryEnum $category): string => match ($category) {
                    ContactCategoryEnum::BUG => '🐞 ' . $this->translator->trans('contact.category.bug', [], 'navigation'),
                    ContactCategoryEnum::SUGGESTION => '💡 ' . $this->translator->trans('contact.category.suggestion', [], 'navigation'),
                    ContactCategoryEnum::QUESTION => '❓ ' . $this->translator->trans('contact.category.question', [], 'navigation'),
                    ContactCategoryEnum::LOVE => '❤️ ' . $this->translator->trans('contact.category.love', [], 'navigation'),
                    ContactCategoryEnum::OTHER => '📁 ' . $this->translator->trans('contact.category.other', [], 'navigation'),
                    ContactCategoryEnum::INFORMATIVE => throw new \LogicException('INFORMATIVE is a system-only category, excluded from this form via the explicit choices list.'),
                },
                'placeholder' => $this->translator->trans('contact.field.category_placeholder', [], 'navigation'),
                'label' => $this->translator->trans('contact.field.category', [], 'navigation'),
                'label_attr' => self::LABEL_ATTR,
                'constraints' => [
                    new NotNull(),
                ],
                'attr' => [
                    'class' => 'w-full appearance-none min-h-11 pl-4 pr-10 py-3 rounded-xl bg-white/5 border border-white/10 text-white text-sm outline-none cursor-pointer focus:ring-2 focus:ring-cyan-500 focus:border-transparent transition-all backdrop-blur-sm',
                ],
                'mapped' => false,
            ])
            ->add('subject', TextType::class, [
                'label' => $this->translator->trans('contact.field.subject', [], 'navigation'),
                'label_attr' => self::LABEL_ATTR,
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 3, max: 150),
                ],
                'attr' => [
                    'class' => FieldClassEnum::ATTRIBUTE_FIELD_CLASS->value,
                ],
                'mapped' => false,
            ])
            ->add('message', TextareaType::class, [
                'label' => $this->translator->trans('contact.field.message', [], 'navigation'),
                'label_attr' => self::LABEL_ATTR,
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 10, max: 5000),
                ],
                'attr' => [
                    'class' => FieldClassEnum::ATTRIBUTE_FIELD_CLASS->value,
                    'rows' => 6,
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
            'csrf_token_id' => 'contact',
        ]);
    }
}
