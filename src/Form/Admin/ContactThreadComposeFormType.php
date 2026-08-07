<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Formulaire admin uniquement (EasyAdmin) — messagerie 1-to-1
 * (App\Controller\Admin\ContactThreadCrudController::compose()). Le destinataire est choisi via un
 * autocomplete JS (assets/controllers/admin/recipient_autocomplete_controller.js) qui peuple
 * `recipientId`, jamais saisi directement.
 *
 * @extends AbstractType<null>
 */
final class ContactThreadComposeFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recipientId', HiddenType::class, [
                'constraints' => [
                    new NotBlank(message: $this->translator->trans('admin.thread.error.recipient_required', [], 'admin', LocaleAllowedEnum::FR->value)),
                ],
                'mapped' => false,
            ])
            ->add('category', EnumType::class, [
                'class' => ContactCategoryEnum::class,
                'label' => $this->translator->trans('admin.thread.compose.category_label', [], 'admin', LocaleAllowedEnum::FR->value),
                'choice_label' => fn (ContactCategoryEnum $category): string => $this->translator->trans(
                    'contact.category.' . $category->value,
                    [],
                    'navigation',
                    LocaleAllowedEnum::FR->value,
                ),
                'constraints' => [
                    new NotBlank(),
                ],
                'mapped' => false,
            ])
            ->add('subject', TextType::class, [
                'label' => $this->translator->trans('admin.thread.compose.subject_label', [], 'admin', LocaleAllowedEnum::FR->value),
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 3, max: 150),
                ],
                'mapped' => false,
            ])
            ->add('body', TextareaType::class, [
                'label' => $this->translator->trans('admin.thread.compose.body_label', [], 'admin', LocaleAllowedEnum::FR->value),
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 10, max: 5000),
                ],
                'attr' => [
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
                'enctype' => 'multipart/form-data',
            ],
            'csrf_token_id' => 'contact_thread_admin_compose',
        ]);
    }
}
