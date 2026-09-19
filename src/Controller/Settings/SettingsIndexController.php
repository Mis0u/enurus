<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Constraint\ImageConstraints;
use App\Entity\User;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Form\ChangePasswordFormType;
use App\Service\Dashboard\DashboardUnlockService;
use App\Service\Dashboard\DashboardWidgetUnlockResolver;
use App\Service\Utils\WeightConverterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
final class SettingsIndexController extends AbstractController
{
    public function __construct(
        private readonly WeightConverterService $weightConverter,
        private readonly DashboardUnlockService $dashboardUnlockService,
        private readonly DashboardWidgetUnlockResolver $widgetUnlockResolver,
        private readonly TranslatorInterface $translator,
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
            'dashboardWidgets' => $this->buildDashboardWidgetRows($user),
        ]);
    }

    /**
     * Un widget n'est proposé en réglages qu'une fois débloqué — même source de vérité que le
     * dashboard (`DashboardWidgetUnlockResolver`), pour ne jamais désynchroniser les deux.
     *
     * @return array<array{key: string, label: string, hidden: bool}>
     */
    private function buildDashboardWidgetRows(User $user): array
    {
        $dashboardState = $this->dashboardUnlockService->getStateForUser($user);
        $unlockedWidgets = $this->widgetUnlockResolver->resolve($user, $dashboardState);

        $rows = [];

        foreach ($unlockedWidgets as $widget => $unlocked) {
            if (! $unlocked) {
                continue;
            }

            $rows[] = [
                'key' => $widget,
                'label' => $this->translator->trans(\sprintf('settings.dashboard_widgets.widget.%s', $widget), [], 'navigation'),
                'hidden' => in_array($widget, $user->hiddenWidgets, true),
            ];
        }

        return $rows;
    }
}
