<?php

declare(strict_types=1);

namespace App\Controller\ProfileConnection;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Security\Voter\ProfileConnectionVoter;
use App\Service\Badge\BadgeSyncService;
use App\Service\Badge\BadgeViewBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tous les badges d'une connexion, en lecture seule — même contenu que la section "Tous mes
 * badges" du dashboard, via le partial `badge/_collection.html.twig`. L'identifiant de la route est
 * celui de la `ProfileConnection`, jamais celui du compte affiché.
 */
#[Route(path: [
    'fr' => '/connexions/{id}/badges',
    'en' => '/connections/{id}/badges',
    'it' => '/connessioni/{id}/badge',
    'es' => '/conexiones/{id}/insignias',
    'pt' => '/conexoes/{id}/insignias',
    'de' => '/verbindungen/{id}/abzeichen',
    'nl' => '/verbindingen/{id}/badges',
    'pl' => '/polaczenia/{id}/odznaki',
], name: 'app_profile_connection_badge_list', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class ProfileConnectionBadgeListController extends AbstractController
{
    public function __construct(
        private readonly BadgeSyncService $badgeSyncService,
        private readonly BadgeViewBuilder $badgeViewBuilder,
    ) {
    }

    #[IsGranted(ProfileConnectionVoter::VIEW_BADGES, subject: 'connection')]
    public function __invoke(ProfileConnection $connection): Response
    {
        $viewer = $this->getUser();

        if (! $viewer instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        $subject = $connection->counterpartOf($viewer);

        // Synchro silencieuse : jamais de flash, il irait dans la session de celui qui regarde.
        $progress = $this->badgeSyncService->sync($subject)->progress;

        return $this->render('profile_connection/badge/list.html.twig', [
            'subject' => $subject,
            'connection' => $connection,
            'badges' => $this->badgeViewBuilder->build($subject, $viewer, $progress),
        ]);
    }
}
