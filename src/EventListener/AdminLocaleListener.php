<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Les routes `/admin` (EasyAdmin) n'ont pas de préfixe `_locale` contrairement au reste de l'app —
 * la requête retombe donc sur `default_locale: en` (translation.yaml). `Dashboard::setLocales(['fr'])`
 * ne fait que restreindre le sélecteur de langue d'EasyAdmin, il ne force pas la locale de la
 * requête. Sans ce listener, les champs `DateTimeField` d'EasyAdmin (qui résolvent leur locale via
 * `\Locale::getDefault()`, synchronisé par `Request::setLocale()`) se formatent en anglais (12h
 * AM/PM) au lieu du français attendu par le seul utilisateur de ce back-office.
 *
 * Priorité 10 : après `LocaleListener::onKernelRequest` de Symfony (16), pour ne pas être écrasé.
 */
final readonly class AdminLocaleListener
{
    private const string ADMIN_PATH_PREFIX = '/admin';

    private const string ADMIN_LOCALE = 'fr';

    #[AsEventListener(event: RequestEvent::class, priority: 10)]
    public function onRequestEvent(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (! $event->isMainRequest() || ! str_starts_with($request->getPathInfo(), self::ADMIN_PATH_PREFIX)) {
            return;
        }

        $request->setLocale(self::ADMIN_LOCALE);
    }
}
