<?php

declare(strict_types=1);

namespace App\Service\ExerciseGuide;

use App\Controller\ExerciseGuide\ExerciseGuideShowController;
use App\Service\Seo\LocalizedRouteParameters;

/**
 * Slug d'une fiche exercice dans chaque langue, pour ses versions `hreflang` et le sitemap.
 */
final readonly class ExerciseGuideRouteParameters implements LocalizedRouteParameters
{
    public function __construct(
        private ExerciseGuideSlugger $slugger,
    ) {
    }

    public function supports(string $route): bool
    {
        return ExerciseGuideShowController::ROUTE === $route;
    }

    public function forLocale(array $routeParams, string $targetLocale): array
    {
        $slug = $routeParams['slug'] ?? null;
        $locale = $routeParams['_locale'] ?? null;
        if (! \is_string($slug) || ! \is_string($locale)) {
            return [];
        }

        $guide = $this->slugger->findBySlug($slug, $locale);

        return null === $guide ? [] : [
            'slug' => $this->slugger->slugFor($guide, $targetLocale),
        ];
    }
}
