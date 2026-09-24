<?php

declare(strict_types=1);

namespace App\Controller\ProfileConnection;

use App\Entity\User;
use App\Enum\Dashboard\DashboardWidgetEnum;
use App\Form\ProfileConnectionRequestType;
use App\Service\Dashboard\DashboardUnlockService;
use App\Service\Dashboard\DashboardWidgetUnlockResolver;
use App\Service\ProfileSharing\ProfileConnectionOverviewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: [
    'fr' => '/connexions',
    'en' => '/connections',
    'it' => '/connessioni',
    'es' => '/conexiones',
    'pt' => '/conexoes',
    'de' => '/verbindungen',
    'nl' => '/verbindingen',
    'pl' => '/polaczenia',
], name: 'app_profile_connection_list', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class ProfileConnectionListController extends AbstractController
{
    public function __construct(
        private readonly ProfileConnectionOverviewService $overviewService,
        private readonly DashboardUnlockService $dashboardUnlockService,
        private readonly DashboardWidgetUnlockResolver $widgetUnlockResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        return $this->render('profile_connection/list/index.html.twig', [
            'overview' => $this->overviewService->forUser($user),
            'requestForm' => $this->createForm(ProfileConnectionRequestType::class),
            'isDiscoverable' => $user->isDiscoverable,
            'shareCode' => $user->shareCode,
            'shareWorkoutsChecked' => $user->shareWorkouts && $user->isDiscoverable,
            'nickname' => $user->nickname,
            'sharedWidgets' => $this->buildSharedWidgetRows($user),
        ]);
    }

    /**
     * Widgets encore visibles sur son propre dashboard (`hiddenWidgets` déjà appliqué en amont)
     * et partageables — un widget qu'on masque déjà chez soi n'a pas sa place ici, un widget
     * personnel (Connexions) non plus. `hiddenSharedWidgets` ne fait que restreindre davantage,
     * jamais réafficher. `checked`/`disabled` intègrent en plus le
     * verrou en cascade : impossible d'afficher un widget sur le partage tant que le partage de
     * profil et celui des séances ne sont pas tous les deux actifs (cf. les cascades côté
     * ProfileConnectionSharingToggleController / ProfileConnectionWorkoutSharingToggleController).
     *
     * @return array<array{key: string, label: string, checked: bool, disabled: bool}>
     */
    private function buildSharedWidgetRows(User $user): array
    {
        $dashboardState = $this->dashboardUnlockService->getStateForUser($user);
        $unlockedWidgets = $this->widgetUnlockResolver->resolve($user, $dashboardState);
        $sharingLocked = ! $user->isDiscoverable || ! $user->shareWorkouts;

        $rows = [];

        foreach ($unlockedWidgets as $widget => $unlocked) {
            if (! $unlocked || in_array($widget, $user->hiddenWidgets, true) || ! DashboardWidgetEnum::from($widget)->isShareable()) {
                continue;
            }

            $rows[] = [
                'key' => $widget,
                'label' => $this->translator->trans(\sprintf('settings.dashboard_widgets.widget.%s', $widget), [], 'navigation'),
                'checked' => ! $sharingLocked && ! in_array($widget, $user->hiddenSharedWidgets, true),
                'disabled' => $sharingLocked,
            ];
        }

        return $rows;
    }
}
