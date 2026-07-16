<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\Entity\AccountDeletionService;
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
        private UserService $userService,
        private AccountDeletionService $accountDeletionService,
    ) {
    }

    #[AsEventListener]
    public function onLoginSuccessEvent(LoginSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();

        $this->saveUserLogin($user);
        $this->cancelPendingDeletion($user);
        $this->redirectTo($event, $user);
    }

    private function saveUserLogin(User $user): void
    {
        $user->lastLogin = now();
        $this->userService->save($user);
    }

    private function redirectTo(LoginSuccessEvent $event, User $user): void
    {
        $locale = $user->locale ?? $event->getRequest()->getLocale();

        $url = $this->urlGenerator->generate('app_dashboard', [
            '_locale' => $locale,
        ]);

        $event->setResponse(new RedirectResponse($url));
    }

    private function cancelPendingDeletion(User $user): void
    {
        $this->accountDeletionService->cancelDeletion($user);
    }
}
