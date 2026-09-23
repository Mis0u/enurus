<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Trait\NotifiesBadgeUnlockTrait;
use App\Entity\User;
use App\Service\Badge\BadgeLabelFormatter;
use App\Service\Badge\BadgeSyncService;
use App\Service\Dashboard\DashboardUnlockService;
use App\Service\Dashboard\DashboardViewDataBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function __invoke(): Response
    {
        $user = $this->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        // L'ancienneté progresse sans aucune séance : seul le chargement du dashboard la rattrape.
        $badgeSync = $this->badgeSyncService->sync($user);
        $this->notifyBadgeUnlocks($badgeSync, $user, $this->badgeLabelFormatter);

        $dashboardState = $this->dashboardUnlockService->getStateForUser($user);

        if (0 === $dashboardState->workoutCount) {
            return $this->render('dashboard/dashboard-empty-responsive.html.twig', [
                'user' => $user,
                'dashboardState' => $dashboardState,
            ]);
        }

        $data = $this->viewDataBuilder->build($user, $user, $dashboardState, $badgeSync->progress);

        return $this->render('dashboard/dashboard.html.twig', [
            'user' => $user,
            'data' => $data,
        ]);
    }
}
