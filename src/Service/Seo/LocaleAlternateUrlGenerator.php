<?php

declare(strict_types=1);

namespace App\Service\Seo;

use App\Enum\Translations\LocaleAllowedEnum;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * URLs absolues d'une page pour les moteurs de recherche : sa version de référence (`canonical`) et
 * ses versions dans chaque langue (`hreflang`, sitemap).
 */
final readonly class LocaleAlternateUrlGenerator
{
    private const string HOME_ROUTE = 'app_home';

    /**
     * Pages servies dans les 8 langues mais au contenu français seulement : les CGU n'ont de valeur
     * juridique qu'en français, les autres langues n'affichent qu'un avertissement au-dessus.
     */
    private const array FRENCH_ONLY_ROUTES = ['app_terms'];

    /**
     * @param iterable<LocalizedRouteParameters> $localizedRouteParameters
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        #[AutowireIterator(LocalizedRouteParameters::class)]
        private iterable $localizedRouteParameters = [],
    ) {
    }

    public function isTranslated(string $route): bool
    {
        return ! \in_array($route, self::FRENCH_ONLY_ROUTES, true);
    }

    /**
     * URL de référence de la page, sans paramètre de requête (ex. `?invitation=`) : la version
     * française pour une page au contenu français seulement.
     *
     * @param array<string, mixed> $routeParams paramètres de route, `_locale` compris
     */
    public function canonical(string $route, array $routeParams): string
    {
        if (! $this->isTranslated($route)) {
            $routeParams = [
                '_locale' => LocaleAllowedEnum::FR->value,
            ] + $routeParams;
        }

        return $this->urlGenerator->generate($route, $routeParams, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @param array<string, mixed> $routeParams
     *
     * @return array<string, string> URL indexée par locale
     */
    public function alternates(string $route, array $routeParams = []): array
    {
        $alternates = [];
        foreach (LocaleAllowedEnum::getAllowedLocale() as $locale) {
            $alternates[$locale] = $this->urlInLocale($route, $routeParams, $locale);
        }

        return $alternates;
    }

    /**
     * Version servie quand aucune langue ne correspond au visiteur : la racine pour l'accueil (elle
     * redirige selon la langue du navigateur), la version anglaise ailleurs (locale par défaut).
     *
     * @param array<string, mixed> $routeParams
     */
    public function defaultAlternate(string $route, array $routeParams = []): string
    {
        if (self::HOME_ROUTE === $route) {
            return $this->siteRootUrl();
        }

        return $this->urlInLocale($route, $routeParams, LocaleAllowedEnum::EN->value);
    }

    /**
     * @param array<string, mixed> $routeParams
     */
    private function urlInLocale(string $route, array $routeParams, string $locale): string
    {
        return $this->urlGenerator->generate(
            $route,
            [
                '_locale' => $locale,
            ] + $this->paramsInLocale($route, $routeParams, $locale) + $routeParams,
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    /**
     * @param array<string, mixed> $routeParams
     *
     * @return array<string, mixed> paramètres propres à `$locale`, prioritaires sur ceux de la page
     */
    private function paramsInLocale(string $route, array $routeParams, string $locale): array
    {
        foreach ($this->localizedRouteParameters as $localizedParameters) {
            if ($localizedParameters->supports($route)) {
                return $localizedParameters->forLocale($routeParams, $locale);
            }
        }

        return [];
    }

    /**
     * La racine `/` n'a pas de route nommée : LocaleRedirectListener l'intercepte avant le routage.
     */
    private function siteRootUrl(): string
    {
        $context = $this->urlGenerator->getContext();
        $scheme = $context->getScheme();
        $port = 'https' === $scheme ? $context->getHttpsPort() : $context->getHttpPort();
        $defaultPort = 'https' === $scheme ? 443 : 80;

        return \sprintf(
            '%s://%s%s%s/',
            $scheme,
            $context->getHost(),
            $port === $defaultPort ? '' : ':' . $port,
            $context->getBaseUrl(),
        );
    }
}
