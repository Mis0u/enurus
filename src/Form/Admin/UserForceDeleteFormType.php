<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ré-authentification avant une action destructive irréversible (suppression cascade immédiate
 * d'un compte) — le mot de passe demandé est celui de l'admin qui agit, jamais celui du compte
 * ciblé.
 *
 * @extends AbstractType<null>
 */
final class UserForceDeleteFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('password', PasswordType::class, [
            'label' => $this->translator->trans('admin.user.force_delete.password_label', [], 'admin', 'fr'),
            'constraints' => [
                new NotBlank(),
            ],
            'mapped' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'user_admin_force_delete',
        ]);
    }
}
