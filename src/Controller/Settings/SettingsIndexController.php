<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Constraint\ImageConstraints;
use App\Entity\User;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Form\ChangePasswordFormType;
use App\Service\Utils\WeightConverterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SettingsIndexController extends AbstractController
{
    public function __construct(
        private readonly WeightConverterService $weightConverter,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings',
            'fr' => '/reglages',
            'it' => '/impostazioni',
            'es' => '/ajustes',
            'pt' => '/definicoes',
            'de' => '/einstellungen',
            'nl' => '/instellingen',
            'pl' => '/ustawienia',
        ],
        name: 'app_settings',
        methods: [Request::METHOD_GET],
    )]
    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('settings/index.html.twig', [
            'user' => $user,
            'nicknameMinLength' => User::NICKNAME_MIN_LENGTH,
            'nicknameMaxLength' => User::NICKNAME_MAX_LENGTH,
            'avatarMaxSizeBytes' => ImageConstraints::MAX_SIZE_BYTES,
            'avatarAllowedMimeTypes' => ImageConstraints::ALLOWED_MIME_TYPES,
            'passwordForm' => $this->createForm(ChangePasswordFormType::class)->createView(),
            'bodyweightMin' => $this->weightConverter->convertToLbs(User::BODYWEIGHT_MIN_KG, $user->unitOfMeasure),
            'bodyweightMax' => $this->weightConverter->convertToLbs(User::BODYWEIGHT_MAX_KG, $user->unitOfMeasure),
            // Les deux unités sont précalculées ici pour permettre au champ de rester cohérent
            // (placeholder + bornes) quand l'utilisateur bascule kg/lbs sans recharger la page —
            // voir bodyweight_controller.js, qui écoute l'évènement "settings:field-updated".
            'bodyweightMinKg' => User::BODYWEIGHT_MIN_KG,
            'bodyweightMaxKg' => User::BODYWEIGHT_MAX_KG,
            'bodyweightMinLbs' => $this->weightConverter->convertToLbs(User::BODYWEIGHT_MIN_KG, UnitOfMeasureEnum::LBS),
            'bodyweightMaxLbs' => $this->weightConverter->convertToLbs(User::BODYWEIGHT_MAX_KG, UnitOfMeasureEnum::LBS),
            'bodyweightDisplay' => null !== $user->bodyweightKg
                ? $this->weightConverter->convertToLbs($user->bodyweightKg, $user->unitOfMeasure)
                : null,
        ]);
    }
}
