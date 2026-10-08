<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Service\Seo\LocaleAlternateUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final readonly class LocaleAlternatesExtension
{
    public function __construct(
        private RequestStack $requestStack,
        private LocaleAlternateUrlGenerator $alternateUrlGenerator,
    ) {
    }

    /**
     * Versions linguistiques de la page courante, plus `x-default` ; vide hors route nommée.
     *
     * @return array<string, string> URL indexée par valeur `hreflang`
     */
    #[AsTwigFunction('locale_alternates')]
    public function localeAlternates(): array
    {
        $request = $this->requestStack->getMainRequest();
        $route = $request?->attributes->get('_route');
        if (! \is_string($route)) {
            return [];
        }

        /** @var array<string, mixed> $routeParams */
        $routeParams = $request?->attributes->get('_route_params', []) ?? [];

        return $this->alternateUrlGenerator->alternates($route, $routeParams)
            + [
                'x-default' => $this->alternateUrlGenerator->defaultAlternate($route, $routeParams),
            ];
    }
}
