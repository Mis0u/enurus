<?php

declare(strict_types=1);

namespace App\Controller\ProfileConnection;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Security\Voter\ProfileConnectionVoter;
use App\Service\Dashboard\DashboardUnlockService;
use App\Service\Dashboard\DashboardViewDataBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Dashboard d'une connexion, en lecture seule. L'identifiant de la route est celui de la
 * `ProfileConnection`, jamais celui du compte affiché : aucun identifiant d'utilisateur n'est
 * exposé, et le Voter a directement son sujet.
 */
#[Route(path: [
    'fr' => '/connexions/{id}/tableau-de-bord',
    'en' => '/connections/{id}/dashboard',
    'it' => '/connessioni/{id}/cruscotto',
    'es' => '/conexiones/{id}/panel',
    'pt' => '/conexoes/{id}/painel',
    'de' => '/verbindungen/{id}/uebersicht',
    'nl' => '/verbindingen/{id}/overzicht',
    'pl' => '/polaczenia/{id}/panel',
], name: 'app_profile_connection_dashboard', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class ProfileConnectionDashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardUnlockService $dashboardUnlockService,
        private readonly DashboardViewDataBuilder $viewDataBuilder,
    ) {
    }

    #[IsGranted(ProfileConnectionVoter::VIEW, subject: 'connection')]
    public function __invoke(ProfileConnection $connection): Response
    {
        $viewer = $this->getUser();

        if (! $viewer instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        $subject = $connection->counterpartOf($viewer);
        $dashboardState = $this->dashboardUnlockService->getStateForUser($subject);

        if (0 === $dashboardState->workoutCount) {
            return $this->render('profile_connection/dashboard/empty.html.twig', [
                'subject' => $subject,
            ]);
        }

        return $this->render('profile_connection/dashboard/index.html.twig', [
            'subject' => $subject,
            'data' => $this->viewDataBuilder->build($subject, $viewer, $dashboardState),
        ]);
    }
}
