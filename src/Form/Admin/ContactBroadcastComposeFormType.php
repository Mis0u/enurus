<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Formulaire admin uniquement (EasyAdmin) — envoi groupé
 * (App\Controller\Admin\ContactBroadcastCrudController::compose()), sans destinataire unitaire —
 * seule la cible (langue ou tous) se choisit. `category` détermine si c'est une diffusion classique
 * (`INFORMATIVE`) ou un sondage (`VOTE`) — dans les deux cas jamais répondable (cf.
 * ContactThreadVoter). `pollOptions` (JSON d'un tableau de libellés, construit en JS côté
 * `assets/controllers/admin/contact_poll_options_controller.js`) et `pollDurationDays` ne sont
 * validés/utilisés que pour `VOTE` — cf. ContactBroadcastCrudController::handleCompose().
 *
 * @extends AbstractType<null>
 */
final class ContactBroadcastComposeFormType extends AbstractType
{
    public const string TARGET_ALL = 'all';

    private const int MIN_POLL_DURATION_DAYS = 1;

    private const int MAX_POLL_DURATION_DAYS = 365;

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

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', ChoiceType::class, [
                'label' => $this->translator->trans('admin.broadcast.field.category', [], 'admin', 'fr'),
                'choices' => [
                    '📢 ' . $this->translator->trans('admin.broadcast.category.informative', [], 'admin', 'fr') => ContactCategoryEnum::INFORMATIVE->value,
                    '🗳️ ' . $this->translator->trans('admin.broadcast.category.vote', [], 'admin', 'fr') => ContactCategoryEnum::VOTE->value,
                ],
                'choice_translation_domain' => false,
                'choice_attr' => static fn (): array => [
                    'data-admin--contact-poll-options-target' => 'categoryInput',
                    'data-action' => 'change->admin--contact-poll-options#onCategoryChange',
                ],
                'expanded' => true,
                'data' => ContactCategoryEnum::INFORMATIVE->value,
                'constraints' => [
                    new NotBlank(),
                ],
                'mapped' => false,
            ])
            ->add('pollOptions', HiddenType::class, [
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'data-admin--contact-poll-options-target' => 'hiddenOptions',
                ],
            ])
            ->add('pollDurationDays', IntegerType::class, [
                'label' => $this->translator->trans('admin.broadcast.field.poll_duration', [], 'admin', 'fr'),
                'required' => false,
                'attr' => [
                    'min' => self::MIN_POLL_DURATION_DAYS,
                    'max' => self::MAX_POLL_DURATION_DAYS,
                ],
                'mapped' => false,
            ])
            ->add('target', ChoiceType::class, [
                'label' => $this->translator->trans('admin.broadcast.field.target', [], 'admin', 'fr'),
                'choices' => $this->targetChoices(),
                'choice_translation_domain' => false,
                'expanded' => true,
                'constraints' => [
                    new NotBlank(),
                ],
                'mapped' => false,
            ])
            ->add('subject', TextType::class, [
                'label' => $this->translator->trans('admin.broadcast.compose.subject_label', [], 'admin', 'fr'),
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 3, max: 150),
                ],
                'mapped' => false,
            ])
            ->add('body', TextareaType::class, [
                'label' => $this->translator->trans('admin.broadcast.field.body', [], 'admin', 'fr'),
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
