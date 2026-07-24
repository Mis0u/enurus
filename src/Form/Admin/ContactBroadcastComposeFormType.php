<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Enum\Translations\LocaleAllowedEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire admin uniquement (EasyAdmin) — envoi groupé
 * (App\Controller\Admin\ContactBroadcastCrudController::compose()). Toujours en catégorie
 * `INFORMATIVE` (forcée côté serveur, jamais demandée ici) et sans destinataire unitaire —
 * seule la cible (langue ou tous) se choisit.
 *
 * @extends AbstractType<null>
 */
final class ContactBroadcastComposeFormType extends AbstractType
{
    public const string TARGET_ALL = 'all';

    /**
     * Même mapping que templates/settings/_fields/_language.html.twig — seule source de vérité
     * pour l'association locale → drapeau dans le projet.
     */
    private const array LOCALE_FLAGS = [
        'fr' => '🇫🇷',
        'en' => '🇬🇧',
        'it' => '🇮🇹',
        'es' => '🇪🇸',
        'pt' => '🇵🇹',
        'de' => '🇩🇪',
        'nl' => '🇳🇱',
        'pl' => '🇵🇱',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('target', ChoiceType::class, [
                'label' => 'Destinataires',
                'choices' => $this->targetChoices(),
                'choice_translation_domain' => false,
                'expanded' => true,
                'constraints' => [
                    new NotBlank(),
                ],
                'mapped' => false,
            ])
            ->add('subject', TextType::class, [
                'label' => 'Sujet',
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 3, max: 150),
                ],
                'mapped' => false,
            ])
            ->add('body', TextareaType::class, [
                'label' => 'Message',
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
            'csrf_token_id' => 'contact_broadcast_admin_compose',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function targetChoices(): array
    {
        $choices = [
            '🌍 Tous les utilisateurs' => self::TARGET_ALL,
        ];

        foreach (LocaleAllowedEnum::cases() as $locale) {
            $choices[self::LOCALE_FLAGS[$locale->value] . ' ' . $locale->value] = $locale->value;
        }

        return $choices;
    }
}
