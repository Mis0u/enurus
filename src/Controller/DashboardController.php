<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\NotifiesBadgeUnlockTrait;
use App\Entity\User;
use App\Enum\Onboarding\GuidedTourStepEnum;
use App\Service\Badge\BadgeLabelFormatter;
use App\Service\Badge\BadgeSyncService;
use App\Service\Dashboard\DashboardUnlockService;
use App\Service\Dashboard\DashboardViewDataBuilder;
use App\Service\Onboarding\GuidedTourService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DashboardController extends AbstractController
{
    use NotifiesBadgeUnlockTrait;

    public function __construct(
        private readonly DashboardUnlockService $dashboardUnlockService,
        private readonly DashboardViewDataBuilder $viewDataBuilder,
        private readonly BadgeSyncService $badgeSyncService,
        private readonly BadgeLabelFormatter $badgeLabelFormatter,
        private readonly GuidedTourService $guidedTourService,
    ) {
    }

    #[Route(
        path: [
            'en' => '/dashboard',
            'fr' => '/tableau-de-bord',
            'it' => '/cruscotto',
            'es' => '/panel',
            'pt' => '/painel',
            'de' => '/uebersicht',
            'nl' => '/overzicht',
            'pl' => '/panel',
        ],
        name: 'app_dashboard'
    )]
    #[IsGranted('ROLE_USER')]
    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        // L'ancienneté progresse sans aucune séance : seul le chargement du dashboard la rattrape.
        $badgeSync = $this->badgeSyncService->sync($user);
        $this->notifyBadgeUnlocks($badgeSync, $user, $this->badgeLabelFormatter);

        $dashboardState = $this->dashboardUnlockService->getStateForUser($user);
        $guidedTourSteps = $this->guidedTourSteps($user, $request);

        if (0 === $dashboardState->workoutCount) {
            return $this->render('dashboard/dashboard-empty-responsive.html.twig', [
                'user' => $user,
                'dashboardState' => $dashboardState,
                'guidedTourSteps' => $guidedTourSteps,
            ]);
        }

        $data = $this->viewDataBuilder->build($user, $user, $dashboardState, $badgeSync->progress);

        return $this->render('dashboard/dashboard.html.twig', [
            'user' => $user,
            'data' => $data,
            'guidedTourSteps' => $guidedTourSteps,
        ]);
    }

    /**
     * Étapes du tour guidé à jouer, vide s'il ne doit pas s'afficher : tout premier affichage du
     * dashboard, ou relance depuis la page Aide (`?tour=1`).
     *
     * @return list<GuidedTourStepEnum>
     */
    private function guidedTourSteps(User $user, Request $request): array
    {
        // Premier affichage consommé même en cas de relance explicite, pour ne pas le rejouer ensuite.
        $isFirstDisplay = $this->guidedTourService->consumeFirstDisplay($user);

        return $isFirstDisplay || $request->query->getBoolean('tour') ? GuidedTourStepEnum::cases() : [];
    }
}
