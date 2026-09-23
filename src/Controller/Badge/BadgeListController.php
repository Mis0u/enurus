<?php

declare(strict_types=1);

namespace App\Controller\Badge;

use App\Controller\Trait\NotifiesBadgeUnlockTrait;
use App\Entity\User;
use App\Service\Badge\BadgeLabelFormatter;
use App\Service\Badge\BadgeSyncService;
use App\Service\Badge\BadgeViewBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tous les badges de l'utilisateur connecté (lien "Mes badges" de la sidebar, widget Badges,
 * bouton de la popup de déblocage). Toujours accessible, même si le widget Badges est masqué
 * dans les réglages : l'option ne masque que le widget du dashboard.
 */
#[Route(path: [
    'fr' => '/mes-badges',
    'en' => '/my-badges',
    'it' => '/i-miei-badge',
    'es' => '/mis-insignias',
    'pt' => '/minhas-insignias',
    'de' => '/meine-abzeichen',
    'nl' => '/mijn-badges',
    'pl' => '/moje-odznaki',
], name: 'app_badge_list', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class BadgeListController extends AbstractController
{
    use NotifiesBadgeUnlockTrait;

    public function __construct(
        private readonly BadgeSyncService $badgeSyncService,
        private readonly BadgeViewBuilder $badgeViewBuilder,
        private readonly BadgeLabelFormatter $badgeLabelFormatter,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        $badgeSync = $this->badgeSyncService->sync($user);
        $this->notifyBadgeUnlocks($badgeSync, $user, $this->badgeLabelFormatter);

        return $this->render('badge/list.html.twig', [
            'user' => $user,
            'badges' => $this->badgeViewBuilder->build($user, $user, $badgeSync->progress),
        ]);
    }
}
