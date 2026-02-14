<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\Entity\UserService;
use function Symfony\Component\Clock\now;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final readonly class LoginSuccessListener
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserService $userService
    ) {
    }

    #[AsEventListener]
    public function onLoginSuccessEvent(LoginSuccessEvent $event): void
    {
        $this->saveUserLogin($event);

        $this->redirectTo($event);
    }

    private function saveUserLogin(LoginSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();

        $user->setLastLogin(now());
        $this->userService->save($user);
    }

    private function redirectTo(LoginSuccessEvent $event): void
    {
        $url = $this->urlGenerator->generate('app_dashboard', [
            '_locale' => $event->getRequest()->getLocale(),
        ]);

        $event->setResponse(new RedirectResponse($url));
    }
}
