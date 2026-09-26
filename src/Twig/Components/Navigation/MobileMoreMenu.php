<?php

declare(strict_types=1);

namespace App\Twig\Components\Navigation;

use App\Entity\User;
use App\Repository\ContactThreadMessageRepository;
use App\Repository\ProfileConnectionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Bouton « Plus » de la barre de navigation mobile et son panneau : toutes les pages absentes de
 * la barre du bas. Les compteurs sont lus une seule fois, pour les tuiles et pour le point de
 * notification du bouton.
 */
#[AsTwigComponent]
final class MobileMoreMenu
{
    /**
     * Préfixes des routes des pages du panneau (listes et sous-pages) : le bouton « Plus » reste en
     * surbrillance tant que l'utilisateur y navigue.
     */
    private const array PANEL_ROUTE_PREFIXES = [
        'app_routine',
        'app_profile_connection',
        'app_badge',
        'app_contact',
        'app_settings',
    ];

    public int $pendingConnectionCount = 0;

    public int $unreadMessageCount = 0;

    public bool $isAdmin = false;

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly ProfileConnectionRepository $profileConnectionRepository,
        private readonly ContactThreadMessageRepository $contactThreadMessageRepository,
    ) {
    }

    public function mount(): void
    {
        $user = $this->security->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('The mobile navigation is only rendered for a logged-in user.');
        }

        $this->isAdmin = $this->security->isGranted('ROLE_ADMIN');
        $this->pendingConnectionCount = $this->profileConnectionRepository->countPendingReceivedBy($user);
        // Un admin n'a ni messagerie ni contact (cf. la sidebar) : rien à compter.
        $this->unreadMessageCount = $this->isAdmin ? 0 : $this->contactThreadMessageRepository->countUnreadForUser($user);
    }

    public function isActive(): bool
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->getString('_route') ?? '';

        foreach (self::PANEL_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function hasNotification(): bool
    {
        return 0 < $this->pendingConnectionCount || 0 < $this->unreadMessageCount;
    }
}
