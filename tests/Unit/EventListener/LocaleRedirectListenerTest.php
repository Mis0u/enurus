<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\LocaleRedirectListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LocaleRedirectListenerTest extends TestCase
{
    public function testRootPathIsRedirectedToLoginUsingThePreferredBrowserLanguage(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_login', [
                '_locale' => 'fr',
            ])
            ->willReturn('/fr/connexion');

        $listener = new LocaleRedirectListener($urlGenerator);
        $event = $this->createEvent('/', acceptLanguage: 'fr-FR,fr;q=0.9,en;q=0.8');

        $listener->onRequestEvent($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame('/fr/connexion', $response->headers->get('Location'));
    }

    public function testNonRootPathIsIgnored(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $listener = new LocaleRedirectListener($urlGenerator);
        $event = $this->createEvent('/fr/tableau-de-bord');

        $listener->onRequestEvent($event);

        self::assertNull($event->getResponse());
    }

    public function testSubRequestOnRootPathIsIgnored(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $listener = new LocaleRedirectListener($urlGenerator);
        $event = $this->createEvent('/', mainRequest: false);

        $listener->onRequestEvent($event);

        self::assertNull($event->getResponse());
    }

    private function createEvent(string $pathInfo, string $acceptLanguage = 'en', bool $mainRequest = true): RequestEvent
    {
        $request = Request::create($pathInfo);
        $request->headers->set('Accept-Language', $acceptLanguage);

        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent(
            $kernel,
            $request,
            $mainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );
    }
}
