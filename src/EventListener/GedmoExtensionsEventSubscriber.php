<?php

declare(strict_types=1);

namespace App\EventListener;

use Gedmo\Blameable\BlameableListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class GedmoExtensionsEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private BlameableListener $blameableListener,
        private ?AuthorizationCheckerInterface $authorizationChecker = null,
        private ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['configureBlameableListener'],
        ];
    }

    public function configureBlameableListener(RequestEvent $event): void
    {
        // Une route stateless (ex. sitemap) n'a pas d'utilisateur connecté : lire le token ouvrirait
        // la session, ce que Symfony refuse sur ce type de requête.
        if (! $event->isMainRequest() || $event->getRequest()->attributes->getBoolean('_stateless')) {
            return;
        }

        if (null === $this->authorizationChecker || null === $this->tokenStorage) {
            return;
        }

        $token = $this->tokenStorage->getToken();

        if (null !== $token && $this->authorizationChecker->isGranted('IS_AUTHENTICATED')) {
            $this->blameableListener->setUserValue($token->getUser());
        }
    }
}
