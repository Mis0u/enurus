<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\User;
use App\EventListener\GedmoExtensionsEventSubscriber;
use Gedmo\Blameable\BlameableListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class GedmoExtensionsEventSubscriberTest extends TestCase
{
    public function testAuthenticatedUserBecomesTheBlameableUser(): void
    {
        $user = new User();
        $blameableListener = $this->createMock(BlameableListener::class);
        $blameableListener->expects(self::once())->method('setUserValue')->with($user);

        $subscriber = new GedmoExtensionsEventSubscriber(
            $blameableListener,
            $this->createAuthorizationChecker(),
            $this->createTokenStorage($user),
        );

        $subscriber->configureBlameableListener($this->createEvent());
    }

    public function testStatelessRequestNeverReadsTheSession(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::never())->method('getToken');

        $subscriber = new GedmoExtensionsEventSubscriber(
            $this->createStub(BlameableListener::class),
            $this->createAuthorizationChecker(),
            $tokenStorage,
        );

        $subscriber->configureBlameableListener($this->createEvent(stateless: true));
    }

    public function testSubRequestIsIgnored(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::never())->method('getToken');

        $subscriber = new GedmoExtensionsEventSubscriber(
            $this->createStub(BlameableListener::class),
            $this->createAuthorizationChecker(),
            $tokenStorage,
        );

        $subscriber->configureBlameableListener($this->createEvent(mainRequest: false));
    }

    private function createAuthorizationChecker(): AuthorizationCheckerInterface
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        return $authorizationChecker;
    }

    private function createTokenStorage(User $user): TokenStorageInterface
    {
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($user, 'main', ['ROLE_USER']));

        return $tokenStorage;
    }

    private function createEvent(bool $stateless = false, bool $mainRequest = true): RequestEvent
    {
        $request = Request::create('/fr/tableau-de-bord');
        $request->attributes->set('_stateless', $stateless);

        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            $mainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );
    }
}
