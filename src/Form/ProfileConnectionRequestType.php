<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Service\ProfileSharing\RandomShareCodeGenerator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array{query: string}>
 */
final class ProfileConnectionRequestType extends AbstractType
{
    private const int SEPARATOR_LENGTH = 1;

    private const int SURROUNDING_SPACES_MARGIN = 4;

    /**
     * Alias + `#` + code, avec une marge pour les espaces autour du séparateur.
     */
    private const int QUERY_MAX_LENGTH = User::NICKNAME_MAX_LENGTH + self::SEPARATOR_LENGTH + RandomShareCodeGenerator::LENGTH + self::SURROUNDING_SPACES_MARGIN;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('query', TextType::class, [
            'label' => 'profile_connection.list.request.label',
            'translation_domain' => 'navigation',
            'constraints' => [
                new NotBlank(),
                new Length(max: self::QUERY_MAX_LENGTH),
            ],
            'attr' => [
                'placeholder' => 'profile_connection.list.request.placeholder',
                'maxlength' => self::QUERY_MAX_LENGTH,
                'autocomplete' => 'off',
                'autocapitalize' => 'none',
                'spellcheck' => 'false',
                'aria-describedby' => 'profile-connection-request-help',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'action' => $this->urlGenerator->generate('app_profile_connection_request'),
            'method' => 'POST',
            'csrf_token_id' => 'profile_connection_request',
        ]);
    }
}
