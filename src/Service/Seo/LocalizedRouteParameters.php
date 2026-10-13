<?php

declare(strict_types=1);

namespace App\Service\Seo;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Paramètres de route qui changent d'une langue à l'autre (ex. slug traduit d'une fiche exercice) :
 * sans eux, les versions `hreflang` et le sitemap reprendraient le slug de la langue courante.
 */
#[AutoconfigureTag]
interface LocalizedRouteParameters
{
    public function supports(string $route): bool;

    /**
     * @param array<string, mixed> $routeParams paramètres de la page dans sa langue, `_locale` compris
     *
     * @return array<string, mixed> les mêmes paramètres pour `$targetLocale` (sans `_locale`)
     */
    public function forLocale(array $routeParams, string $targetLocale): array;
}
