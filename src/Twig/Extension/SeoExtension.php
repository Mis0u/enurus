<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Enum\Translations\LocaleAllowedEnum;
use App\Service\Seo\LocaleAlternateUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * Données de la page courante destinées aux moteurs de recherche et aux aperçus de lien.
 */
final readonly class SeoExtension
{
    public function __construct(
        private RequestStack $requestStack,
        private LocaleAlternateUrlGenerator $alternateUrlGenerator,
    ) {
    }

    /**
     * Versions linguistiques de la page courante, plus `x-default` ; vide hors route nommée ou pour
     * une page au contenu non traduit.
     *
     * @return array<string, string> URL indexée par valeur `hreflang`
     */
    #[AsTwigFunction('locale_alternates')]
    public function localeAlternates(): array
    {
        $route = $this->currentRoute();
        if (null === $route || ! $this->alternateUrlGenerator->isTranslated($route)) {
            return [];
        }

        $routeParams = $this->currentRouteParams();

        return $this->alternateUrlGenerator->alternates($route, $routeParams)
            + [
                'x-default' => $this->alternateUrlGenerator->defaultAlternate($route, $routeParams),
            ];
    }

    /**
     * URL de référence de la page courante ; vide hors route nommée.
     */
    #[AsTwigFunction('canonical_url')]
    public function canonicalUrl(): string
    {
        $route = $this->currentRoute();
        if (null === $route) {
            return '';
        }

        return $this->alternateUrlGenerator->canonical($route, $this->currentRouteParams());
    }

    #[AsTwigFunction('og_locale')]
    public function ogLocale(): string
    {
        $locale = LocaleAllowedEnum::tryFrom($this->mainRequest()?->getLocale() ?? '') ?? LocaleAllowedEnum::EN;

        return $locale->ogLocale();
    }

    private function currentRoute(): ?string
    {
        $route = $this->mainRequest()?->attributes->get('_route');

        return \is_string($route) ? $route : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function currentRouteParams(): array
    {
        /** @var array<string, mixed> $routeParams */
        $routeParams = $this->mainRequest()?->attributes->get('_route_params', []) ?? [];

        return $routeParams;
    }

    private function mainRequest(): ?Request
    {
        return $this->requestStack->getMainRequest();
    }
}
