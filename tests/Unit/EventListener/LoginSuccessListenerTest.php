<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\User;
use App\EventListener\LoginSuccessListener;
use App\Service\Entity\AccountDeletionService;
use App\Service\Entity\UserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LoginSuccessListenerTest extends TestCase
{
    public function testOnLoginSuccessSavesLastLoginAndCancelsPendingDeletion(): void
    {
        $user = $this->createUser();

        $userService = $this->createMock(UserService::class);
        $userService->expects(self::once())->method('save')->with($user);

        $accountDeletionService = $this->createMock(AccountDeletionService::class);
        $accountDeletionService->expects(self::once())->method('cancelDeletion')->with($user);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/fr/tableau-de-bord');

        $listener = new LoginSuccessListener($urlGenerator, $userService, $accountDeletionService);
        $listener->onLoginSuccessEvent($this->createEvent($user));

        self::assertGreaterThan(new \DateTimeImmutable('-10 seconds'), $user->lastLogin);
    }

    public function testOnLoginSuccessRedirectsUsingTheUsersOwnLocaleNotTheRequestLocale(): void
    {
        $user = $this->createUser(locale: 'de');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_dashboard', [
                '_locale' => 'de',
            ])
            ->willReturn('/de/dashboard');

        $listener = new LoginSuccessListener(
            $urlGenerator,
            $this->createStub(UserService::class),
            $this->createStub(AccountDeletionService::class),
        );

        // La requête est en anglais, mais la locale enregistrée sur le compte doit primer
        // (règle CLAUDE.md : la locale n'est fixée qu'une fois, au login).
        $event = $this->createEvent($user, requestLocale: 'en');
        $listener->onLoginSuccessEvent($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame('/de/dashboard', $response->headers->get('Location'));
    }

    private function createUser(string $locale = 'fr'): User
    {
        $user = new User();
        $user->email = 'user@test.com';
        $user->nickname = 'User';
        $user->locale = $locale;

        return $user;
    }

    private function createEvent(User $user, string $requestLocale = 'en'): LoginSuccessEvent
    {
        $request = new Request();
        $request->setLocale($requestLocale);

        $passport = new SelfValidatingPassport(new UserBadge($user->email, static fn (): User => $user));

        return new LoginSuccessEvent(
            $this->createStub(AuthenticatorInterface::class),
            $passport,
            $this->createStub(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class),
            $request,
            null,
            'main',
        );
    }
}
