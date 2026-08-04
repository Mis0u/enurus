<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\AdminLocaleListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AdminLocaleListenerTest extends TestCase
{
    public function testAdminPathForcesFrenchLocale(): void
    {
        $listener = new AdminLocaleListener();
        $event = $this->createEvent('/admin/contact-broadcast');

        $listener->onRequestEvent($event);

        self::assertSame('fr', $event->getRequest()->getLocale());
    }

    public function testAdminRootPathForcesFrenchLocale(): void
    {
        $listener = new AdminLocaleListener();
        $event = $this->createEvent('/admin');

        $listener->onRequestEvent($event);

        self::assertSame('fr', $event->getRequest()->getLocale());
    }

    public function testNonAdminPathIsUntouched(): void
    {
        $listener = new AdminLocaleListener();
        $event = $this->createEvent('/en/tableau-de-bord');
        $event->getRequest()->setLocale('en');

        $listener->onRequestEvent($event);

        self::assertSame('en', $event->getRequest()->getLocale());
    }

    public function testSubRequestOnAdminPathIsIgnored(): void
    {
        $listener = new AdminLocaleListener();
        $event = $this->createEvent('/admin/contact-broadcast', mainRequest: false);
        $event->getRequest()->setLocale('en');

        $listener->onRequestEvent($event);

        self::assertSame('en', $event->getRequest()->getLocale());
    }

    private function createEvent(string $pathInfo, bool $mainRequest = true): RequestEvent
    {
        $request = Request::create($pathInfo);

        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent(
            $kernel,
            $request,
            $mainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
        );
    }
}
