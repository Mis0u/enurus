<?php

declare(strict_types=1);

namespace App\Service\Seo;

use App\Enum\Translations\LocaleAllowedEnum;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * URLs absolues d'une même page dans chaque langue, pour les liens `hreflang` et le sitemap.
 */
final readonly class LocaleAlternateUrlGenerator
{
    private const string HOME_ROUTE = 'app_home';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
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
            $alternates[$locale] = $this->urlGenerator->generate(
                $route,
                [
                    '_locale' => $locale,
                ] + $routeParams,
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
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

        return $this->urlGenerator->generate(
            $route,
            [
                '_locale' => LocaleAllowedEnum::EN->value,
            ] + $routeParams,
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
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
