<?php

declare(strict_types=1);

namespace App\Service\Utils;

use Symfony\Component\HttpFoundation\Request;

/**
 * Adresse d'un lien « Retour » : la page précédente (en-tête Referer) quand elle appartient au
 * site, sinon l'adresse de repli. Une page d'un autre site n'est jamais reprise, pour ne pas
 * transformer le lien en redirection vers un domaine tiers.
 */
final class ReturnUrlResolver
{
    public function resolve(Request $request, string $fallbackUrl): string
    {
        $previousUrl = $request->headers->get('Referer');

        if (null === $previousUrl || ! $this->isOtherPageOfTheSite($previousUrl, $request)) {
            return $fallbackUrl;
        }

        return $previousUrl;
    }

    private function isOtherPageOfTheSite(string $previousUrl, Request $request): bool
    {
        $siteRoot = $request->getSchemeAndHttpHost() . '/';

        return str_starts_with($previousUrl, $siteRoot)
            && parse_url($previousUrl, PHP_URL_PATH) !== $request->getPathInfo();
    }
}
