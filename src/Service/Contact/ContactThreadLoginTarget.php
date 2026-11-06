<?php

declare(strict_types=1);

namespace App\Service\Contact;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

/**
 * Le bouton de l'email de message ouvre un fil : une personne déconnectée doit y arriver après sa
 * connexion. Limité à cette page pour que toutes les autres connexions mènent au tableau de bord
 * dans la langue du compte (LoginSuccessListener).
 *
 * Lit la redirection préparée par le form_login : Symfony a déjà retiré la page demandée de la
 * session quand LoginSuccessEvent est émis. Seul le chemin est gardé, jamais un autre domaine.
 *
 * Non `final` : doublé par `LoginSuccessListenerTest`.
 */
readonly class ContactThreadLoginTarget
{
    private const string THREAD_ROUTE = 'app_contact_thread_show';

    public function __construct(
        #[Autowire(service: 'router')]
        private RequestMatcherInterface $requestMatcher,
    ) {
    }

    public function resolve(?Response $loginResponse): ?string
    {
        if (! $loginResponse instanceof RedirectResponse) {
            return null;
        }

        $path = Request::create($loginResponse->getTargetUrl())->getPathInfo();

        return $this->isThreadPage($path) ? $path : null;
    }

    private function isThreadPage(string $path): bool
    {
        try {
            $route = $this->requestMatcher->matchRequest(Request::create($path));
        } catch (ExceptionInterface) {
            return false;
        }

        return self::THREAD_ROUTE === ($route['_route'] ?? null);
    }
}
