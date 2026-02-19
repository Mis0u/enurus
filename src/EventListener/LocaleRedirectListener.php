<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Enum\Translations\LocaleAllowedEnum;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class LocaleRedirectListener
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[AsEventListener(event: RequestEvent::class, priority: 33)]
    public function onRequestEvent(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (! $event->isMainRequest() || '/' !== $request->getPathInfo()) {
            return;
        }

        $locale = $request->getPreferredLanguage(LocaleAllowedEnum::getAllowedLocale());
        $url = $this->urlGenerator->generate('app_login', [
            '_locale' => $locale,
        ]);
        $event->setResponse(new RedirectResponse($url));
    }
}
